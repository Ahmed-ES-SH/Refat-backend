<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a correlation id, exposes it to logs and the response.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->fromHeader($request) ?? (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    /**
     * Accept only a short, safe id from the client so a hostile header cannot
     * poison the log stream.
     */
    private function fromHeader(Request $request): ?string
    {
        $candidate = (string) $request->header('X-Request-ID', '');

        return preg_match('/^[A-Za-z0-9._-]{1,64}$/', $candidate) === 1 ? $candidate : null;
    }
}
