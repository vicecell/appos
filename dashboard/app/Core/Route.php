<?php

declare(strict_types=1);

namespace ServerWatch\Core;

use ServerWatch\Authz\Permission;

final class Route
{
    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly mixed $handler,
        public readonly bool $auth,
        public readonly ?Permission $permission,
        public readonly string $regex,
    ) {
    }
}
