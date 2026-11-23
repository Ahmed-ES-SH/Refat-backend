<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Re-send the verification mail.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            throw new ApiException('EMAIL_ALREADY_VERIFIED', __('auth.email_already_verified'), 409);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => __('auth.verification_sent')], 202);
    }
}
