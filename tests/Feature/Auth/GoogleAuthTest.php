<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
    ]);
});

function fakeGoogleUser(array $attributes = []): SocialiteUser
{
    return SocialiteUser::fake(array_merge([
        'id' => 'google-123',
        'name' => 'Sara Al-Otaibi',
        'email' => 'sara@example.com',
        'email_verified' => true,
    ], $attributes));
}

it('redirects to Google', function () {
    Socialite::fake('google');

    $this->get('/api/v1/auth/google/redirect')
        ->assertRedirect('https://socialite.fake/google/authorize');
});

it('creates a verified patient from a new Google identity', function () {
    Socialite::fake('google', fakeGoogleUser());

    $this->get('/api/v1/auth/google/callback')
        ->assertRedirect(config('app.frontend_url').'/auth/callback');

    $user = User::firstWhere('email', 'sara@example.com');

    expect($user)->not->toBeNull()
        ->and($user->google_id)->toBe('google-123')
        ->and($user->role->value)->toBe('patient')
        ->and($user->password)->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue();

    $this->assertAuthenticatedAs($user, 'web');
});

it('signs in an already linked account without creating a duplicate', function () {
    $user = User::factory()->create([
        'email' => 'sara@example.com',
        'google_id' => 'google-123',
    ]);

    Socialite::fake('google', fakeGoogleUser(['email' => 'changed@example.com']));

    $this->get('/api/v1/auth/google/callback')
        ->assertRedirect(config('app.frontend_url').'/auth/callback');

    expect(User::count())->toBe(1);
    $this->assertAuthenticatedAs($user, 'web');
});

it('links a verified existing account with the same email', function () {
    $user = User::factory()->create(['email' => 'sara@example.com']);

    Socialite::fake('google', fakeGoogleUser());

    $this->get('/api/v1/auth/google/callback')
        ->assertRedirect(config('app.frontend_url').'/auth/callback');

    expect(User::count())->toBe(1)
        ->and($user->refresh()->google_id)->toBe('google-123');

    $this->assertAuthenticatedAs($user, 'web');
});

it('refuses to link an unverified existing account', function () {
    $user = User::factory()->unverified()->create(['email' => 'sara@example.com']);

    Socialite::fake('google', fakeGoogleUser());

    $this->get('/api/v1/auth/google/callback')
        ->assertRedirect(config('app.frontend_url').'/login?error=GOOGLE_EMAIL_CONFLICT');

    expect($user->refresh()->google_id)->toBeNull();
    $this->assertGuest('web');
});

it('refuses an account already linked to another Google identity', function () {
    $user = User::factory()->create([
        'email' => 'sara@example.com',
        'google_id' => 'other-google-id',
    ]);

    Socialite::fake('google', fakeGoogleUser());

    $this->get('/api/v1/auth/google/callback')
        ->assertRedirect(config('app.frontend_url').'/login?error=GOOGLE_EMAIL_CONFLICT');

    expect($user->refresh()->google_id)->toBe('other-google-id');
    $this->assertGuest('web');
});

it('refuses a Google email that Google has not verified', function () {
    Socialite::fake('google', fakeGoogleUser(['email_verified' => false]));

    $this->get('/api/v1/auth/google/callback')
        ->assertRedirect(config('app.frontend_url').'/login?error=GOOGLE_EMAIL_UNVERIFIED');

    expect(User::count())->toBe(0);
    $this->assertGuest('web');
});

it('refuses a Google identity without an email', function () {
    Socialite::fake('google', fakeGoogleUser(['email' => '']));

    $this->get('/api/v1/auth/google/callback')
        ->assertRedirect(config('app.frontend_url').'/login?error=GOOGLE_EMAIL_UNVERIFIED');

    expect(User::count())->toBe(0);
});

it('refuses a suspended account', function () {
    User::factory()->suspended()->create([
        'email' => 'sara@example.com',
        'google_id' => 'google-123',
    ]);

    Socialite::fake('google', fakeGoogleUser());

    $this->get('/api/v1/auth/google/callback')
        ->assertRedirect(config('app.frontend_url').'/login?error=ACCOUNT_SUSPENDED');

    $this->assertGuest('web');
});

it('redirects with an error when Google is not configured', function () {
    config([
        'services.google.client_id' => null,
        'services.google.client_secret' => null,
    ]);

    $this->get('/api/v1/auth/google/redirect')
        ->assertRedirect(config('app.frontend_url').'/login?error=GOOGLE_NOT_CONFIGURED');
});

it('redirects with an error when consent is denied', function () {
    Socialite::fake('google', fn () => throw new RuntimeException('access_denied'));

    $this->get('/api/v1/auth/google/callback')
        ->assertRedirect(config('app.frontend_url').'/login?error=GOOGLE_AUTH_FAILED');

    $this->assertGuest('web');
});
