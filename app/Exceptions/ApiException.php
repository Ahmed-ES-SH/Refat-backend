<?php

namespace App\Exceptions;

use Exception;

/**
 * A business or protocol error that renders through the standard API envelope.
 *
 * Every subclass-free usage carries a stable machine-readable `$errorCode`
 * so the frontend can branch on it instead of parsing messages.
 */
class ApiException extends Exception
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 400,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
