<?php

declare(strict_types=1);

namespace ServerWatch\Core;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(int $status, array $data): self
    {
        return new self(
            $status,
            [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * Structured error per spec: {error:{code,message,request_id}} (+ details for validation).
     *
     * @param array<string, string>|null $details
     */
    public static function error(
        int $status,
        string $code,
        string $message,
        string $requestId,
        ?array $details = null,
    ): self {
        $error = ['code' => $code, 'message' => $message, 'request_id' => $requestId];
        if ($details !== null) {
            $error['details'] = $details;
        }
        return self::json($status, ['error' => $error]);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, [...$this->headers, $name => $value], $this->body);
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->status, [...$this->headers, ...$headers], $this->body);
    }
}
