<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    /**
     * Register a patient, sign them in and send the verification mail.
     */
    public function store(RegisterRequest $request): JsonResponse
    {
        $user = new User($request->safe()->only(['name', 'email', 'password', 'phone']));
        $user->role = UserRole::Patient;
        $user->status = UserStatus::Active;
        $user->locale = $request->enum('locale', Locale::class) ?? Locale::Ar;
        $user->save();

        $user->sendEmailVerificationNotification();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return UserResource::make($user)->response()->setStatusCode(201);
    }
}
