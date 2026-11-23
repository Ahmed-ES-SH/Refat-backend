<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('registers a patient, signs them in and sends the verification mail', function () {
    Notification::fake();

    $response = asSpa()->postJson('/api/v1/auth/register', [
        'name' => 'Sara Al-Otaibi',
        'email' => 'Sara@Example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'sara@example.com')
        ->assertJsonPath('data.role', 'patient')
        ->assertJsonPath('data.locale', 'ar')
        ->assertJsonPath('data.email_verified', false)
        ->assertJsonPath('data.center_id', null);

    $user = User::firstWhere('email', 'sara@example.com');

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('ignores a role sent by the client', function () {
    asSpa()->postJson('/api/v1/auth/register', [
        'name' => 'Sara',
        'email' => 'sara@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin',
        'status' => 'active',
    ])->assertCreated();

    expect(User::firstWhere('email', 'sara@example.com')->role->value)->toBe('patient');
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'sara@example.com']);

    asSpa()->postJson('/api/v1/auth/register', [
        'name' => 'Sara',
        'email' => 'SARA@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('email');
});

it('rejects a weak or unconfirmed password', function () {
    asSpa()->postJson('/api/v1/auth/register', [
        'name' => 'Sara',
        'email' => 'sara@example.com',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('accepts an explicit locale', function () {
    asSpa()->postJson('/api/v1/auth/register', [
        'name' => 'Sara',
        'email' => 'sara@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'locale' => 'en',
    ])
        ->assertCreated()
        ->assertJsonPath('data.locale', 'en');
});
