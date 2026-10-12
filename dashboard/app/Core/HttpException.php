<?php

declare(strict_types=1);

namespace ServerWatch\Core;

use RuntimeException;

/**
 * An error that is safe to show to the client: stable code, generic message, optional field details.
 */
class HttpException extends RuntimeException
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string>|null $details
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $headers = [],
        public readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }

    /**
     * @param array<string, string> $details field => problem
     */
    public static function validation(array $details): self
    {
        return new self(422, 'validation_failed', 'Request validation failed.', [], $details);
    }

    public static function notFound(): self
    {
        return new self(404, 'not_found', 'Resource not found.');
    }
}
