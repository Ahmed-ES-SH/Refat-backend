<?php

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('sends a reset link that points at the frontend', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'sara@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'sara@example.com'])
        ->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, config('app.frontend_url').'/reset-password?')
            && str_contains($url, 'token=')
            && str_contains($url, 'email=sara%40example.com');
    });
});

it('answers 200 for an unknown email so accounts cannot be enumerated', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
        ->assertOk();

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create(['email' => 'sara@example.com']);
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'sara@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    expect(Hash::check('new-password-123', $user->refresh()->password))->toBeTrue();
});

it('rejects a reused token', function () {
    $user = User::factory()->create(['email' => 'sara@example.com']);
    $token = Password::broker()->createToken($user);

    $payload = [
        'token' => $token,
        'email' => 'sara@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ];

    $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();

    $this->postJson('/api/v1/auth/reset-password', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'INVALID_RESET_TOKEN');
});

it('rejects an expired token', function () {
    $user = User::factory()->create(['email' => 'sara@example.com']);
    $token = Password::broker()->createToken($user);

    $this->travel(61)->minutes();

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'sara@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'INVALID_RESET_TOKEN');
});

it('throttles repeated reset requests', function () {
    User::factory()->create(['email' => 'sara@example.com']);

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'sara@example.com'])->assertOk();
    }

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'sara@example.com'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'TOO_MANY_REQUESTS');
});
