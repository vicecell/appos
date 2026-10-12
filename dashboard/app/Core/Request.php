<?php

declare(strict_types=1);

namespace ServerWatch\Core;

final class Request
{
    public const MAX_BODY_BYTES = 65536;

    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers lower-case names
     * @param array<string, string> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $headers = [],
        public readonly array $cookies = [],
        public readonly ?string $rawBody = null,
        public readonly string $ip = '0.0.0.0',
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isUnsafeMethod(): bool
    {
        return in_array(strtoupper($this->method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public static function fromGlobals(): self
    {
        $method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($uri, PHP_URL_PATH);

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value) && str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $server => $name) {
            if (is_string($_SERVER[$server] ?? null)) {
                $headers[$name] = $_SERVER[$server];
            }
        }

        $query = [];
        foreach ($_GET as $k => $v) {
            if (is_string($v)) {
                $query[(string) $k] = $v;
            }
        }
        $cookies = [];
        foreach ($_COOKIE as $k => $v) {
            if (is_string($v)) {
                $cookies[(string) $k] = $v;
            }
        }

        $body = null;
        if ($method !== 'GET' && $method !== 'HEAD') {
            // Read one byte over the limit so oversized bodies are detectable without buffering everything.
            $read = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
            $body = $read === false ? null : $read;
        }

        // REMOTE_ADDR only: X-Forwarded-For is attacker-controlled until a trusted-proxy list exists.
        $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';

        return new self($method, is_string($path) ? $path : '/', $query, $headers, $cookies, $body, $ip);
    }
}
