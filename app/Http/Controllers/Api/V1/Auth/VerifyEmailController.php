<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * Handle the signed link from the verification email.
     *
     * The signature proves control of the mailbox, so no session is required.
     */
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new ApiException('INVALID_SIGNATURE', __('auth.invalid_signature'), 403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->away($this->frontendUrl('already'));
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return redirect()->away($this->frontendUrl('verified'));
    }

    private function frontendUrl(string $status): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/auth/verified?status='.$status;
    }
}
