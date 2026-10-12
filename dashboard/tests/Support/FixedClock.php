<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use ServerWatch\Core\Clock;

final class FixedClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $iso = '2026-10-12T06:00:00Z')
    {
        $this->now = new DateTimeImmutable($iso, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify(sprintf('%+d seconds', $seconds));
    }
}
