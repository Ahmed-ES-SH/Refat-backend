<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks unverified accounts from booking and purchasing.
 *
 * Registered as the `verified` alias so future phases can attach it to a route.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasVerifiedEmail()) {
            throw new ApiException('EMAIL_NOT_VERIFIED', __('auth.email_not_verified'), 403);
        }

        return $next($request);
    }
}
