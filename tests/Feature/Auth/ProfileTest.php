<?php

use App\Enums\Gender;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

it('returns the profile contract with role permissions', function () {
    $user = User::factory()->create([
        'name' => 'Sara Al-Otaibi',
        'email' => 'sara@example.com',
        'phone' => '0551234567',
        'date_of_birth' => '1994-04-12',
        'gender' => Gender::Female,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Sara Al-Otaibi')
        ->assertJsonPath('data.role', 'patient')
        ->assertJsonPath('data.email_verified', true)
        ->assertJsonPath('data.center_id', null)
        ->assertJsonPath('data.phone', '0551234567')
        ->assertJsonPath('data.date_of_birth', '1994-04-12')
        ->assertJsonPath('data.gender', 'female')
        ->assertJsonPath('data.permissions', config('permissions.roles.patient'))
        ->assertJsonStructure(['data' => [
            'id', 'name', 'email', 'role', 'email_verified', 'locale', 'center_id', 'permissions',
        ]]);
});

it('updates the profile', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', [
        'name' => 'New Name',
        'phone' => '0500000000',
        'date_of_birth' => '1990-01-01',
        'gender' => 'male',
        'locale' => 'en',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.locale', 'en');

    expect($user->refresh()->phone)->toBe('0500000000');
});

it('rejects a future date of birth', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/v1/me', ['date_of_birth' => now()->addYear()->toDateString()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_of_birth');
});

it('changes the password only with the current password', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/me/password', [
        'current_password' => 'not-my-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    $this->putJson('/api/v1/me/password', [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    expect(Hash::check('new-password-123', $user->refresh()->password))->toBeTrue();
});

it('rejects a new password identical to the current one', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/v1/me/password', [
        'current_password' => 'password',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

it('changes the email, clears verification and resends the link', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'old@example.com']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me/email', [
        'email' => 'New@Example.com',
        'current_password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.email', 'new@example.com')
        ->assertJsonPath('data.email_verified', false);

    expect($user->refresh()->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('requires the current password to change the email', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me/email', [
        'email' => 'new@example.com',
        'current_password' => 'wrong',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect($user->refresh()->email)->toBe('old@example.com');
});

it('rejects an email already used by another account', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    Sanctum::actingAs(User::factory()->create(['email' => 'old@example.com']));

    $this->patchJson('/api/v1/me/email', [
        'email' => 'taken@example.com',
        'current_password' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('blocks a suspended account on authenticated routes', function () {
    Sanctum::actingAs(User::factory()->suspended()->create());

    $this->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'ACCOUNT_SUSPENDED');
});
