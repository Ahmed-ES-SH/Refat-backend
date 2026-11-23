<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the request locale from `Accept-Language: ar|en`.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locale::fromHeader($request->header('Accept-Language'))
            ?? Locale::tryFrom((string) config('app.locale'))
            ?? Locale::En;

        app()->setLocale($locale->value);

        return $next($request);
    }
}
