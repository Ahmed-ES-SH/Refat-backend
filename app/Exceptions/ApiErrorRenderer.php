<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every `/api/*` error as `{ message, code, errors }`.
 *
 * Returning `null` hands the exception back to Laravel's default renderer, so
 * non-API requests keep their normal behaviour.
 */
class ApiErrorRenderer
{
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        [$status, $code, $message, $errors] = $this->describe($e);

        $payload = ['message' => $message, 'code' => $code];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, array<int, string>>}
     */
    private function describe(Throwable $e): array
    {
        if ($e instanceof ApiException) {
            return [$e->status, $e->errorCode, $e->getMessage(), $e->errors];
        }

        if ($e instanceof ValidationException) {
            return [422, 'VALIDATION_ERROR', $e->getMessage(), $e->errors()];
        }

        if ($e instanceof AuthenticationException) {
            return [401, 'UNAUTHENTICATED', __('auth.unauthenticated'), []];
        }

        if ($e instanceof InvalidSignatureException) {
            return [403, 'INVALID_SIGNATURE', __('auth.invalid_signature'), []];
        }

        if ($e instanceof ThrottleRequestsException) {
            return [429, 'TOO_MANY_REQUESTS', __('auth.throttle', ['seconds' => $this->retryAfter($e)]), []];
        }

        if ($e instanceof NotFoundHttpException) {
            return [404, 'NOT_FOUND', __('http.not_found'), []];
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return [
                $status,
                $this->codeForStatus($status),
                $e->getMessage() !== '' ? $e->getMessage() : $this->codeForStatus($status),
                [],
            ];
        }

        return [
            500,
            'SERVER_ERROR',
            config('app.debug') ? $e->getMessage() : __('http.server_error'),
            [],
        ];
    }

    private function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            default => $status >= 500 ? 'SERVER_ERROR' : 'BAD_REQUEST',
        };
    }

    private function retryAfter(ThrottleRequestsException $e): int
    {
        $retryAfter = $e->getHeaders()['Retry-After'] ?? 60;

        return (int) $retryAfter;
    }
}
