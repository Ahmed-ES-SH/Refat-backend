<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateEmailRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return UserResource::make($user);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->password = $request->validated('password');
        $user->save();

        return response()->json(['message' => __('auth.password_changed')]);
    }

    /**
     * Changing the email clears verification and sends a fresh link.
     */
    public function updateEmail(UpdateEmailRequest $request): UserResource
    {
        $user = $request->user();
        $user->email = $request->validated('email');
        $user->email_verified_at = null;
        $user->save();

        $user->sendEmailVerificationNotification();

        return UserResource::make($user);
    }
}
