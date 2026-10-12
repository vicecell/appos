<?php

declare(strict_types=1);

namespace ServerWatch\Core;

use ServerWatch\Authz\Outcome;
use ServerWatch\Http\Guard;

/**
 * Request pipeline: route match, authentication, CSRF, tenant authorization, handler.
 * Everything fails closed: any unexpected error becomes a generic 500, never an implicit allow.
 */
final class Kernel
{
    /** @var \Closure(): string */
    private \Closure $requestIdFactory;
    /** @var \Closure(string): void */
    private \Closure $errorLogger;

    /**
     * @param (\Closure(): string)|null $requestIdFactory
     * @param (\Closure(string): void)|null $errorLogger receives a single-line, secret-free description
     */
    public function __construct(
        private readonly Router $router,
        ?\Closure $requestIdFactory = null,
        ?\Closure $errorLogger = null,
        private readonly ?Guard $guard = null,
    ) {
        $this->requestIdFactory = $requestIdFactory ?? static fn (): string => bin2hex(random_bytes(8));
        $this->errorLogger = $errorLogger ?? static function (string $line): void {
            error_log($line);
        };
    }

    public function handle(Request $request): Response
    {
        $requestId = ($this->requestIdFactory)();
        try {
            return $this->dispatch($request, $requestId)->withHeader('X-Request-Id', $requestId);
        } catch (HttpException $e) {
            return Response::error($e->status, $e->errorCode, $e->getMessage(), $requestId, $e->details)
                ->withHeaders($e->headers)
                ->withHeader('X-Request-Id', $requestId);
        } catch (\Throwable $e) {
            // Log class and request id only: exception messages can embed hostnames, SQL, or credentials.
            ($this->errorLogger)(sprintf('unhandled %s request_id=%s', $e::class, $requestId));
            return Response::error(500, 'internal_error', 'Internal server error.', $requestId)
                ->withHeader('X-Request-Id', $requestId);
        }
    }

    private function dispatch(Request $request, string $requestId): Response
    {
        $match = $this->router->match($request->method, $request->path);
        $route = $match->route;
        if ($route === null) {
            if ($match->allowedMethods !== []) {
                return Response::error(405, 'method_not_allowed', 'Method not allowed.', $requestId)
                    ->withHeader('Allow', implode(', ', $match->allowedMethods));
            }
            throw HttpException::notFound();
        }

        $ctx = new RequestContext($request, $match->params, $requestId);

        if ($route->auth) {
            $guard = $this->guard ?? throw new \LogicException('Authenticated route without a guard');
            $principal = $guard->authenticate($request);
            if ($principal === null) {
                throw new HttpException(401, 'unauthenticated', 'Authentication required.');
            }
            if ($request->isUnsafeMethod() && !$guard->csrfValid($request)) {
                throw new HttpException(403, 'csrf_invalid', 'Invalid or missing CSRF token.');
            }
            $ctx->principal = $principal;

            if ($route->permission !== null) {
                $tenant = $ctx->idParam('tenant');   // malformed tenant id: indistinguishable 404
                $outcome = $guard->authorize($principal, $tenant, $route->permission, $request->ip);
                if ($outcome === Outcome::NotFound) {
                    throw HttpException::notFound();
                }
                if ($outcome === Outcome::Forbidden) {
                    throw new HttpException(403, 'forbidden', 'You do not have permission to perform this action.');
                }
                $ctx->organizationId = $tenant;
            }
        }

        $handler = $route->handler;
        if (!is_callable($handler)) {
            throw new \LogicException('Route handler is not callable');
        }
        $response = $handler($ctx);
        if (!$response instanceof Response) {
            throw new \LogicException('Route handler must return a Response');
        }
        return $response;
    }
}
