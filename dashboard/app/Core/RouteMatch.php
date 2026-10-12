<?php

declare(strict_types=1);

namespace ServerWatch\Core;

final class RouteMatch
{
    /**
     * @param array<string, string> $params
     * @param list<string> $allowedMethods only populated when the path exists with other methods
     */
    public function __construct(
        public readonly ?Route $route,
        public readonly array $params = [],
        public readonly array $allowedMethods = [],
    ) {
    }
}
