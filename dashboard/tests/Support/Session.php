<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Support;

final class Session
{
    public function __construct(public readonly string $token, public readonly string $csrf)
    {
    }
}
