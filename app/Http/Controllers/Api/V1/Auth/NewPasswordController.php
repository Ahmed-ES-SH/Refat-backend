<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class NewPasswordController extends Controller
{
    /**
     * Consume a single-use reset token and set the new password.
     */
    public function store(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->password = $password;
                $user->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new ApiException(
                'INVALID_RESET_TOKEN',
                __($status),
                422,
                ['email' => [__($status)]],
            );
        }

        return response()->json(['message' => __('auth.password_reset')]);
    }
}
