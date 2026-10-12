<?php

declare(strict_types=1);

namespace ServerWatch\Core;

use ServerWatch\Authz\Permission;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /**
     * Routes are PUBLIC unless auth: true. A permission implies a {tenant} segment that the kernel authorizes.
     */
    public function add(
        string $method,
        string $pattern,
        mixed $handler,
        bool $auth = false,
        ?Permission $permission = null,
    ): void {
        if ($permission !== null && !$auth) {
            throw new \LogicException('A permission requires an authenticated route');
        }
        if ($permission !== null && !str_contains($pattern, '{tenant}')) {
            throw new \LogicException('A permission-protected route must contain a {tenant} segment');
        }
        $regex = '#^' . preg_replace('/\\\\\{([a-z_]+)\\\\\}/', '(?P<$1>[^/]+)', preg_quote($pattern, '#')) . '$#';
        $this->routes[] = new Route(strtoupper($method), $pattern, $handler, $auth, $permission, (string) $regex);
    }

    public function match(string $method, string $path): RouteMatch
    {
        $method = strtoupper($method);
        $allowed = [];
        foreach ($this->routes as $route) {
            if (preg_match($route->regex, $path, $m) !== 1) {
                continue;
            }
            if ($route->method === $method) {
                $params = [];
                foreach ($m as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                return new RouteMatch($route, $params);
            }
            $allowed[] = $route->method;
        }
        $allowed = array_values(array_unique($allowed));
        sort($allowed);
        return new RouteMatch(null, [], $allowed);
    }
}
