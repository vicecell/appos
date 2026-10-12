<?php

declare(strict_types=1);

namespace ServerWatch\Core;

/**
 * Minimal request dispatcher. SW-001 serves only /health; the real router,
 * session/CSRF and tenant authorization middleware arrive in SW-002.
 */
final class Kernel
{
    /** @var \Closure(): string */
    private \Closure $requestIdFactory;

    /**
     * @param (\Closure(): string)|null $requestIdFactory
     */
    public function __construct(?\Closure $requestIdFactory = null)
    {
        $this->requestIdFactory = $requestIdFactory ?? static fn (): string => bin2hex(random_bytes(8));
    }

    public function handle(string $method, string $path): Response
    {
        $requestId = ($this->requestIdFactory)();
        $method = strtoupper($method);

        if ($path === '/health') {
            if ($method !== 'GET') {
                return Response::error(405, 'method_not_allowed', 'Method not allowed.', $requestId)
                    ->withHeader('Allow', 'GET');
            }
            return Response::json(200, ['status' => 'ok', 'service' => 'serverwatch-dashboard'])
                ->withHeader('X-Request-Id', $requestId);
        }

        return Response::error(404, 'not_found', 'Resource not found.', $requestId)
            ->withHeader('X-Request-Id', $requestId);
    }
}
