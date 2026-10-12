<?php

declare(strict_types=1);

namespace ServerWatch\Core;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
