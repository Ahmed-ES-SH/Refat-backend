<?php

use App\Models\User;

it('logs a patient in and returns their profile', function () {
    $user = User::factory()->create(['email' => 'sara@example.com']);

    asSpa()->postJson('/api/v1/auth/login', [
        'email' => 'sara@example.com',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.role', 'patient');

    $this->assertAuthenticatedAs($user);
});

it('allows an unverified user to sign in', function () {
    User::factory()->unverified()->create(['email' => 'sara@example.com']);

    asSpa()->postJson('/api/v1/auth/login', [
        'email' => 'sara@example.com',
        'password' => 'password',
    ])->assertOk()->assertJsonPath('data.email_verified', false);
});

it('rejects a wrong password without revealing the account', function () {
    User::factory()->create(['email' => 'sara@example.com']);

    asSpa()->postJson('/api/v1/auth/login', [
        'email' => 'sara@example.com',
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'INVALID_CREDENTIALS')
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('rejects an unknown email with the same response', function () {
    asSpa()->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'INVALID_CREDENTIALS');
});

it('blocks a suspended account after the password is verified', function () {
    User::factory()->suspended()->create(['email' => 'sara@example.com']);

    asSpa()->postJson('/api/v1/auth/login', [
        'email' => 'sara@example.com',
        'password' => 'password',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'ACCOUNT_SUSPENDED');

    $this->assertGuest();
});

it('does not reveal that a suspended account exists when the password is wrong', function () {
    User::factory()->suspended()->create(['email' => 'sara@example.com']);

    asSpa()->postJson('/api/v1/auth/login', [
        'email' => 'sara@example.com',
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'INVALID_CREDENTIALS');
});

it('throttles repeated login attempts', function () {
    User::factory()->create(['email' => 'sara@example.com']);

    foreach (range(1, 5) as $attempt) {
        asSpa()->postJson('/api/v1/auth/login', [
            'email' => 'sara@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    asSpa()->postJson('/api/v1/auth/login', [
        'email' => 'sara@example.com',
        'password' => 'wrong-password',
    ])
        ->assertStatus(429)
        ->assertJsonPath('code', 'TOO_MANY_REQUESTS');
});

it('logs the user out', function () {
    $user = User::factory()->create(['email' => 'sara@example.com']);

    asSpa()->postJson('/api/v1/auth/login', [
        'email' => 'sara@example.com',
        'password' => 'password',
    ])->assertOk();

    asSpa()->postJson('/api/v1/auth/logout')->assertNoContent();

    // `auth:sanctum` makes Sanctum the default guard for the rest of the test
    // process, so the session guard has to be named explicitly.
    $this->assertGuest('web');
});

it('rejects an unauthenticated profile request', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});
