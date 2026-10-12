<?php

declare(strict_types=1);

namespace ServerWatch\Core;

use DateTimeImmutable;
use DateTimeZone;

/**
 * All persisted timestamps are UTC DATETIME(3).
 */
final class Time
{
    public static function db(DateTimeImmutable $t): string
    {
        return $t->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    public static function parse(string $dbValue): DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');
        $t = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.v', $dbValue, $utc);
        if ($t === false) {
            $t = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dbValue, $utc);
        }
        if ($t === false) {
            throw new \UnexpectedValueException('Unparseable database timestamp');
        }
        return $t;
    }

    /**
     * DATETIME(3) value to RFC 3339 UTC, e.g. 2026-10-12T06:00:00.000Z
     */
    public static function iso(string $dbValue): string
    {
        return self::parse($dbValue)->format('Y-m-d\TH:i:s.v\Z');
    }
}
