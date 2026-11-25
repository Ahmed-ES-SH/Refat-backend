<?php

use App\Enums\UserRole;
use App\Models\Center;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->payload = [
        'name' => 'Al Noor Medical Center',
        'description' => 'Family care center',
        'phone' => '0112345678',
        'email' => 'center@example.com',
        'city' => 'Riyadh',
        'address' => 'King Fahd Road',
        'country_code' => 'SA',
        'timezone' => 'Asia/Riyadh',
        'license_no' => 'LIC-2026-01',
        'staff_name' => 'Nadia Ali',
        'staff_email' => 'nadia@example.com',
        'staff_password' => 'Str0ng!Pass',
    ];
});

it('registers a center with a pending status and a staff account', function () {
    $this->postJson('/api/v1/centers/register', $this->payload)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Al Noor Medical Center')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.approved_at', null)
        ->assertJsonPath('data.staff.0.email', 'nadia@example.com');

    $center = Center::firstWhere('name', 'Al Noor Medical Center');

    expect($center)->not->toBeNull()
        ->and($center->status->value)->toBe('pending')
        ->and($center->approved_at)->toBeNull();

    $staff = User::firstWhere('email', 'nadia@example.com');

    expect($staff->role)->toBe(UserRole::CenterStaff)
        ->and($staff->center_id)->toBe($center->id)
        ->and($staff->email_verified_at)->toBeNull();
});

it('emails a verification link instead of pre-verifying the staff account', function () {
    $this->postJson('/api/v1/centers/register', $this->payload)->assertCreated();

    $staff = User::firstWhere('email', 'nadia@example.com');

    expect($staff->email_verified_at)->toBeNull();

    Notification::assertSentTo($staff, VerifyEmail::class);
});

it('rejects an incomplete registration payload', function () {
    $this->postJson('/api/v1/centers/register', ['name' => 'No Contact'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone', 'email', 'city', 'staff_email', 'staff_password']);

    expect(Center::count())->toBe(0);
});

it('rejects a staff email that is already taken', function () {
    User::factory()->create(['email' => 'nadia@example.com']);

    $this->postJson('/api/v1/centers/register', $this->payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('staff_email');

    expect(Center::count())->toBe(0);
});

it('rejects a weak staff password', function () {
    $this->postJson('/api/v1/centers/register', [
        ...$this->payload,
        'staff_password' => 'weak',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('staff_password');
});

it('rejects a center name wider than the column', function () {
    $this->postJson('/api/v1/centers/register', [
        ...$this->payload,
        'name' => str_repeat('a', 151),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('rejects a timezone that is not an IANA identifier', function () {
    $this->postJson('/api/v1/centers/register', [
        ...$this->payload,
        'timezone' => 'UTC+3',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('timezone');

    expect(Center::count())->toBe(0);
});
