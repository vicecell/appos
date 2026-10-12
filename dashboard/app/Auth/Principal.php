<?php

declare(strict_types=1);

namespace ServerWatch\Auth;

final class Principal
{
    public function __construct(
        public readonly int $userId,
        public readonly bool $isPlatformAdmin,
    ) {
    }
}
