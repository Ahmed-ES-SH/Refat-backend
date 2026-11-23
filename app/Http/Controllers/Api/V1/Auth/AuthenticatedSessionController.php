<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Start a session for a patient, staff member or admin.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        if (! Auth::guard('web')->attempt($request->only('email', 'password'))) {
            throw new ApiException(
                'INVALID_CREDENTIALS',
                __('auth.invalid_credentials'),
                422,
                ['email' => [__('auth.failed')]],
            );
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        // Credentials are checked first so the status cannot be probed without the password.
        if ($user->isSuspended()) {
            $this->endSession($request);

            throw new ApiException('ACCOUNT_SUSPENDED', __('auth.account_suspended'), 403);
        }

        $request->session()->regenerate();

        return UserResource::make($user)->response();
    }

    public function destroy(Request $request): Response
    {
        $this->endSession($request);

        return response()->noContent();
    }

    private function endSession(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
