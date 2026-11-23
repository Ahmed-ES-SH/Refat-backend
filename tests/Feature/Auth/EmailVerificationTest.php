<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

function verificationUrl(User $user, ?int $minutes = 60, ?string $hash = null): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
        'id' => $user->id,
        'hash' => $hash ?? sha1($user->email),
    ]);
}

it('verifies the email from a signed link', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrl($user))
        ->assertRedirect(config('app.frontend_url').'/auth/verified?status=verified');

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a tampered link', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrl($user, hash: sha1('someone-else@example.com')))
        ->assertForbidden()
        ->assertJsonPath('code', 'INVALID_SIGNATURE');

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects an expired link', function () {
    $user = User::factory()->unverified()->create();

    $url = verificationUrl($user);

    $this->travel(61)->minutes();

    $this->get($url)
        ->assertForbidden()
        ->assertJsonPath('code', 'INVALID_SIGNATURE');

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('reports an already verified account without failing', function () {
    $user = User::factory()->create();

    $this->get(verificationUrl($user))
        ->assertRedirect(config('app.frontend_url').'/auth/verified?status=already');
});

it('resends the verification mail', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/email/verification-notification')
        ->assertStatus(202);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('refuses to resend when the email is already verified', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/email/verification-notification')
        ->assertStatus(409)
        ->assertJsonPath('code', 'EMAIL_ALREADY_VERIFIED');
});

it('blocks unverified users on routes guarded by the verified middleware', function () {
    Route::middleware(['api', 'auth:sanctum', 'verified'])
        ->get('/api/v1/_test/verified-only', fn () => response()->json(['data' => 'ok']));

    Sanctum::actingAs(User::factory()->unverified()->create());

    $this->getJson('/api/v1/_test/verified-only')
        ->assertForbidden()
        ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/_test/verified-only')->assertOk();
});
