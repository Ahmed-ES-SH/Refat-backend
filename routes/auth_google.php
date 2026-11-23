<?php

use App\Http\Controllers\Api\V1\Auth\GoogleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Google OAuth
|--------------------------------------------------------------------------
|
| Registered from bootstrap/app.php inside the `web` middleware group so the
| OAuth `state` stored in the session survives the round trip to Google.
|
*/

Route::prefix('api/v1/auth/google')
    ->middleware('throttle:api')
    ->group(function (): void {
        Route::get('redirect', [GoogleController::class, 'redirect'])
            ->name('api.v1.auth.google.redirect');

        Route::get('callback', [GoogleController::class, 'callback'])
            ->name('api.v1.auth.google.callback');
    });
