<?php

use App\Http\Controllers\Api\V1\Admin\CenterAdminController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\Centers\AvailabilityRuleController;
use App\Http\Controllers\Api\V1\Centers\CenterDirectoryController;
use App\Http\Controllers\Api\V1\Centers\CenterProfileController;
use App\Http\Controllers\Api\V1\Centers\CenterRegistrationController;
use App\Http\Controllers\Api\V1\Centers\DayOffController;
use App\Http\Controllers\Api\V1\Centers\DoctorController;
use App\Http\Controllers\Api\V1\Family\FamilyMemberController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Loaded by bootstrap/app.php under the `api` middleware group and the
| `/api` prefix, so every path here is relative to `/api`.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::get('ping', fn () => response()->json(['data' => ['pong' => true]]))
        ->name('api.v1.ping');

    // Public authentication endpoints.
    Route::post('auth/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:register')
        ->name('api.v1.auth.register');

    Route::post('auth/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('api.v1.auth.login');

    Route::post('auth/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('api.v1.auth.password.email');

    Route::post('auth/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('api.v1.auth.password.store');

    // Clicked from a mailbox, so it must work without a session.
    Route::get('auth/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware('signed')
        ->whereNumber('id')
        ->name('verification.verify');

    // Public center registration (throttled)
    Route::post('centers/register', [CenterRegistrationController::class, 'store'])
        ->middleware('throttle:center-register')
        ->name('api.v1.centers.register');

    // Public center directory
    Route::get('centers', [CenterDirectoryController::class, 'index'])
        ->name('api.v1.centers.index');

    Route::get('centers/{center}', [CenterDirectoryController::class, 'show'])
        ->whereNumber('center')
        ->name('api.v1.centers.show');

    // Admin center management
    Route::middleware(['auth:sanctum', 'active'])->prefix('admin')->group(function (): void {
        Route::post('centers/{center}/approve', [CenterAdminController::class, 'approve'])
            ->middleware('can:approve,center')
            ->whereNumber('center')
            ->name('api.v1.admin.centers.approve');

        Route::post('centers/{center}/suspend', [CenterAdminController::class, 'suspend'])
            ->middleware('can:suspend,center')
            ->whereNumber('center')
            ->name('api.v1.admin.centers.suspend');
    });

    // Authenticated endpoints.
    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::post('auth/logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('api.v1.auth.logout');

        Route::post('auth/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:verification')
            ->name('api.v1.auth.verification.send');

        Route::get('me', [ProfileController::class, 'show'])
            ->name('api.v1.me.show');

        Route::patch('me', [ProfileController::class, 'update'])
            ->name('api.v1.me.update');

        Route::put('me/password', [ProfileController::class, 'updatePassword'])
            ->middleware('throttle:sensitive')
            ->name('api.v1.me.password');

        Route::patch('me/email', [ProfileController::class, 'updateEmail'])
            ->middleware('throttle:sensitive')
            ->name('api.v1.me.email');

        Route::get('me/family-members', [FamilyMemberController::class, 'index'])
            ->name('api.v1.family.index');

        Route::post('me/family-members', [FamilyMemberController::class, 'store'])
            ->name('api.v1.family.store');

        Route::get('me/family-members/{familyMember}', [FamilyMemberController::class, 'show'])
            ->whereNumber('familyMember')
            ->name('api.v1.family.show');

        Route::patch('me/family-members/{familyMember}', [FamilyMemberController::class, 'update'])
            ->whereNumber('familyMember')
            ->name('api.v1.family.update');

        Route::delete('me/family-members/{familyMember}', [FamilyMemberController::class, 'destroy'])
            ->whereNumber('familyMember')
            ->name('api.v1.family.destroy');

        // Center staff doctor management (center_staff scope their own center)
        Route::prefix('center')->group(function (): void {
            Route::get('doctors', [DoctorController::class, 'index'])
                ->name('api.v1.center.doctors.index');

            Route::post('doctors', [DoctorController::class, 'store'])
                ->name('api.v1.center.doctors.store');

            Route::get('doctors/{doctor}', [DoctorController::class, 'show'])
                ->whereNumber('doctor')
                ->name('api.v1.center.doctors.show');

            Route::patch('doctors/{doctor}', [DoctorController::class, 'update'])
                ->whereNumber('doctor')
                ->name('api.v1.center.doctors.update');

            Route::delete('doctors/{doctor}', [DoctorController::class, 'destroy'])
                ->whereNumber('doctor')
                ->name('api.v1.center.doctors.destroy');

            // Weekly availability windows. The `can:` gates run before form
            // validation, so a foreign center gets 404 instead of a 422 that
            // would confirm the record exists.
            Route::get('doctors/{doctor}/availability-rules', [AvailabilityRuleController::class, 'index'])
                ->middleware('can:view,doctor')
                ->whereNumber('doctor')
                ->name('api.v1.center.availability-rules.index');

            Route::post('doctors/{doctor}/availability-rules', [AvailabilityRuleController::class, 'store'])
                ->middleware('can:view,doctor')
                ->whereNumber('doctor')
                ->name('api.v1.center.availability-rules.store');

            Route::get('availability-rules/{rule}', [AvailabilityRuleController::class, 'show'])
                ->middleware('can:view,rule')
                ->whereNumber('rule')
                ->name('api.v1.center.availability-rules.show');

            Route::patch('availability-rules/{rule}', [AvailabilityRuleController::class, 'update'])
                ->middleware('can:update,rule')
                ->whereNumber('rule')
                ->name('api.v1.center.availability-rules.update');

            Route::delete('availability-rules/{rule}', [AvailabilityRuleController::class, 'destroy'])
                ->middleware('can:delete,rule')
                ->whereNumber('rule')
                ->name('api.v1.center.availability-rules.destroy');

            // Full-day exceptions. Same gate order as availability rules:
            // `can:` runs before validation, so foreign records stay 404.
            Route::get('doctors/{doctor}/days-off', [DayOffController::class, 'index'])
                ->middleware('can:view,doctor')
                ->whereNumber('doctor')
                ->name('api.v1.center.days-off.index');

            Route::post('doctors/{doctor}/days-off', [DayOffController::class, 'store'])
                ->middleware('can:view,doctor')
                ->whereNumber('doctor')
                ->name('api.v1.center.days-off.store');

            Route::get('days-off/{dayOff}', [DayOffController::class, 'show'])
                ->middleware('can:view,dayOff')
                ->whereNumber('dayOff')
                ->name('api.v1.center.days-off.show');

            Route::patch('days-off/{dayOff}', [DayOffController::class, 'update'])
                ->middleware('can:update,dayOff')
                ->whereNumber('dayOff')
                ->name('api.v1.center.days-off.update');

            Route::delete('days-off/{dayOff}', [DayOffController::class, 'destroy'])
                ->middleware('can:delete,dayOff')
                ->whereNumber('dayOff')
                ->name('api.v1.center.days-off.destroy');
        });

        // Center staff profile management. The prefix must not collide with the
        // public directory route above: Laravel keys routes by method + URI, so a
        // second GET on `centers/{center}` would silently replace the public one.
        Route::middleware('can:update,center')->prefix('center/profile/{center}')->whereNumber('center')->group(function (): void {
            Route::get('/', [CenterProfileController::class, 'show'])
                ->name('api.v1.centers.profile.show');

            Route::patch('/', [CenterProfileController::class, 'update'])
                ->middleware('throttle:sensitive')
                ->name('api.v1.centers.profile.update');

            Route::delete('/logo', [CenterProfileController::class, 'deleteLogo'])
                ->name('api.v1.centers.profile.logo.destroy');
        });
    });
});
