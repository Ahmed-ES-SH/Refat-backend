<?php

use App\Models\Center;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * A 1x1 PNG, so the upload tests exercise real MIME sniffing.
 */
function centerLogoBytes(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

/**
 * A real upload on disk, so the MIME type is sniffed from the bytes.
 *
 * `UploadedFile::fake()` reports a type derived from the file name, so it
 * cannot prove that the application sniffs content.
 */
function realCenterLogoUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'rifad_logo');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

beforeEach(function () {
    $this->center = Center::factory()->active()->create();
    $this->staff = User::factory()->centerStaff($this->center)->create();
    $this->otherStaff = User::factory()->centerStaff()->create();
});

it('lets center staff read their own center profile', function () {
    Sanctum::actingAs($this->staff);

    $this->getJson("/api/v1/center/profile/{$this->center->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $this->center->id)
        ->assertJsonPath('data.staff.0.email', $this->staff->email);
});

it('lets center staff update their own center but never its name', function () {
    Sanctum::actingAs($this->staff);

    $originalName = $this->center->name;

    $this->patchJson("/api/v1/center/profile/{$this->center->id}", [
        'name' => 'Renamed Center',
        'phone' => '0111111111',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', $originalName)
        ->assertJsonPath('data.staff.0.email', $this->staff->email);

    expect($this->center->refresh()->name)->toBe($originalName)
        ->and($this->center->phone)->toBe('0111111111');
});

it('refuses staff of another center', function () {
    Sanctum::actingAs($this->otherStaff);

    $this->patchJson("/api/v1/center/profile/{$this->center->id}", ['name' => 'Hijacked'])
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');

    expect($this->center->refresh()->name)->not->toBe('Hijacked');
});

it('refuses a patient', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/center/profile/{$this->center->id}")->assertForbidden();
});

it('requires authentication', function () {
    $this->getJson("/api/v1/center/profile/{$this->center->id}")->assertUnauthorized();
});

it('ignores a name wider than the column', function () {
    Sanctum::actingAs($this->staff);

    $originalName = $this->center->name;

    $this->patchJson("/api/v1/center/profile/{$this->center->id}", [
        'name' => str_repeat('a', 151),
    ])->assertOk();

    expect($this->center->refresh()->name)->toBe($originalName);
});

it('stores a logo on the public disk', function () {
    Storage::fake('public');

    Sanctum::actingAs($this->staff);

    $response = $this->patch("/api/v1/center/profile/{$this->center->id}", [
        'logo' => UploadedFile::fake()->createWithContent('logo.png', centerLogoBytes()),
    ]);

    $response->assertOk();

    $logoPath = $this->center->refresh()->logo_path;

    expect($logoPath)->not->toBeNull()
        ->and(Storage::disk('public')->exists($logoPath))->toBeTrue()
        ->and($logoPath)->toStartWith("centers/{$this->center->id}/");
});

it('rejects a logo that is not an image', function () {
    Storage::fake('public');

    Sanctum::actingAs($this->staff);

    $this->patch("/api/v1/center/profile/{$this->center->id}", [
        'logo' => UploadedFile::fake()->createWithContent('logo.txt', 'not an image'),
    ])->assertUnprocessable()->assertJsonValidationErrors('logo');

    expect($this->center->refresh()->logo_path)->toBeNull();
});

it('rejects a logo larger than two megabytes', function () {
    Storage::fake('public');

    Sanctum::actingAs($this->staff);

    $this->patch("/api/v1/center/profile/{$this->center->id}", [
        'logo' => UploadedFile::fake()->createWithContent(
            'logo.png',
            centerLogoBytes().str_repeat(' ', 2 * 1024 * 1024)
        ),
    ])->assertUnprocessable()->assertJsonValidationErrors('logo');

    expect($this->center->refresh()->logo_path)->toBeNull();
});

it('removes the logo from the public disk', function () {
    Storage::fake('public');

    $path = "centers/{$this->center->id}/logo_old.png";
    Storage::disk('public')->put($path, 'bytes');
    $this->center->update(['logo_path' => $path]);

    Sanctum::actingAs($this->staff);

    $this->deleteJson("/api/v1/center/profile/{$this->center->id}/logo")
        ->assertOk()
        ->assertJsonPath('data.logo_path', null);

    expect($this->center->refresh()->logo_path)->toBeNull()
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});

it('tolerates deleting a logo that was never uploaded', function () {
    Sanctum::actingAs($this->staff);

    $this->deleteJson("/api/v1/center/profile/{$this->center->id}/logo")
        ->assertOk()
        ->assertJsonPath('data.logo_path', null);
});

it('refuses to delete the logo of another center', function () {
    $path = "centers/{$this->center->id}/logo_old.png";
    $this->center->update(['logo_path' => $path]);

    Sanctum::actingAs($this->otherStaff);

    $this->deleteJson("/api/v1/center/profile/{$this->center->id}/logo")
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');

    expect($this->center->refresh()->logo_path)->toBe($path);
});

it('requires authentication to delete a logo', function () {
    $this->deleteJson("/api/v1/center/profile/{$this->center->id}/logo")
        ->assertUnauthorized();
});

it('throttles rapid profile updates', function () {
    Sanctum::actingAs($this->staff);

    foreach (range(1, 6) as $attempt) {
        $this->patchJson("/api/v1/center/profile/{$this->center->id}", [
            'phone' => '01111111'.$attempt,
        ])->assertOk();
    }

    $this->patchJson("/api/v1/center/profile/{$this->center->id}", [
        'phone' => '0111111199',
    ])->assertStatus(429);
});

it('replaces an existing logo and removes the previous file', function () {
    Storage::fake('public');

    $previousPath = "centers/{$this->center->id}/logo_previous.png";
    Storage::disk('public')->put($previousPath, 'previous');
    $this->center->update(['logo_path' => $previousPath]);

    Sanctum::actingAs($this->staff);

    $this->patch("/api/v1/center/profile/{$this->center->id}", [
        'logo' => UploadedFile::fake()->createWithContent('logo.png', centerLogoBytes()),
    ])->assertOk();

    $logoPath = $this->center->refresh()->logo_path;

    expect($logoPath)->not->toBe($previousPath)
        ->and(Storage::disk('public')->exists($logoPath))->toBeTrue()
        ->and(Storage::disk('public')->exists($previousPath))->toBeFalse();
});

it('keeps the existing logo when a profile update carries no new file', function () {
    Storage::fake('public');

    $logoPath = "centers/{$this->center->id}/logo_current.png";
    Storage::disk('public')->put($logoPath, 'current');
    $this->center->update(['logo_path' => $logoPath]);

    Sanctum::actingAs($this->staff);

    $this->patchJson("/api/v1/center/profile/{$this->center->id}", [
        'phone' => '0555555555',
    ])->assertOk();

    expect($this->center->refresh()->logo_path)->toBe($logoPath)
        ->and(Storage::disk('public')->exists($logoPath))->toBeTrue();
});

it('keeps the previous logo when the profile save fails', function () {
    Storage::fake('public');

    $previousPath = "centers/{$this->center->id}/logo_previous.png";
    Storage::disk('public')->put($previousPath, 'previous');
    $this->center->update(['logo_path' => $previousPath]);

    Sanctum::actingAs($this->staff);

    Center::updating(static fn (): never => throw new RuntimeException('database unavailable'));

    try {
        $this->patch("/api/v1/center/profile/{$this->center->id}", [
            'logo' => UploadedFile::fake()->createWithContent('logo.png', centerLogoBytes()),
        ])->assertStatus(500);
    } finally {
        Center::flushEventListeners();
    }

    expect($this->center->refresh()->logo_path)->toBe($previousPath)
        ->and(Storage::disk('public')->exists($previousPath))->toBeTrue()
        ->and(Storage::disk('public')->files("centers/{$this->center->id}"))->toBe([$previousPath]);
});

it('stores a logo under an extension sniffed from its content, not its name', function () {
    Storage::fake('public');

    Sanctum::actingAs($this->staff);

    $file = realCenterLogoUpload('payload.txt', centerLogoBytes());

    try {
        $this->patch("/api/v1/center/profile/{$this->center->id}", ['logo' => $file])
            ->assertOk();
    } finally {
        unlink($file->getPathname());
    }

    $logoPath = $this->center->refresh()->logo_path;

    expect($logoPath)->toEndWith('.png')
        ->and(Storage::disk('public')->exists($logoPath))->toBeTrue();
});

it('rejects a timezone that is not an IANA identifier', function () {
    Sanctum::actingAs($this->staff);

    $this->patchJson("/api/v1/center/profile/{$this->center->id}", [
        'timezone' => 'UTC+3',
    ])->assertUnprocessable()->assertJsonValidationErrors('timezone');
});
