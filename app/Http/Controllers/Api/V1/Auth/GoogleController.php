<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Browser-based Google sign-in.
 *
 * These routes live in the `web` group because Socialite stores the OAuth
 * `state` in the session; a stateless group would fail every callback.
 */
class GoogleController extends Controller
{
    /**
     * Send the browser to Google's consent screen.
     */
    public function redirect(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->failure('GOOGLE_NOT_CONFIGURED');
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Resolve the Google identity to an account and start a session.
     */
    public function callback(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->failure('GOOGLE_NOT_CONFIGURED');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Exception) {
            // Consent denied, expired state, or a provider outage. Programming
            // errors (`Error`) are deliberately not caught here.
            return $this->failure('GOOGLE_AUTH_FAILED');
        }

        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $googleId = (string) $googleUser->getId();

        if ($email === '' || data_get($googleUser->getRaw(), 'email_verified', true) === false) {
            return $this->failure('GOOGLE_EMAIL_UNVERIFIED');
        }

        // 1. Already linked → sign in.
        $user = User::firstWhere('google_id', $googleId);

        if ($user === null) {
            // 2. Same email → link, but only to a verified account that is not
            //    already linked to a different Google identity.
            $user = User::firstWhere('email', $email);

            if ($user !== null && ($user->email_verified_at === null || $user->google_id !== null)) {
                return $this->failure('GOOGLE_EMAIL_CONFLICT');
            }

            // 3. New account. Google already verified the address.
            if ($user === null) {
                $user = new User([
                    'name' => $googleUser->getName() ?: Str::before($email, '@'),
                    'email' => $email,
                ]);
                $user->role = UserRole::Patient;
                $user->status = UserStatus::Active;
                $user->locale = Locale::Ar;
                $user->email_verified_at = now();
            }

            $user->google_id = $googleId;
            $user->save();
        }

        if ($user->isSuspended()) {
            return $this->failure('ACCOUNT_SUSPENDED');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->away($this->frontendUrl('/auth/callback'));
    }

    /**
     * A missing client id or secret would otherwise surface as a 500.
     */
    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    /**
     * Failures are browser navigations, so they land on the login screen with
     * the machine code the frontend already knows how to display.
     */
    private function failure(string $code): RedirectResponse
    {
        return redirect()->away($this->frontendUrl('/login').'?error='.$code);
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
