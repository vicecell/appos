<?php

declare(strict_types=1);

namespace ServerWatch\Auth;

/**
 * RFC 6238 TOTP (HMAC-SHA1, 6 digits, 30 s step) with single-use enforcement via the last accepted step.
 */
final class Totp
{
    public const PERIOD = 30;
    private const DIGITS = 6;

    public static function generateSecret(): string
    {
        return random_bytes(20);
    }

    public static function codeAt(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $secret, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);
        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * @return int|null the matched time step, or null when invalid or not newer than $lastStep (replay)
     */
    public static function verify(string $secret, string $code, int $now, ?int $lastStep, int $window = 1): ?int
    {
        if (preg_match('/^[0-9]{6}$/', $code) !== 1) {
            return null;
        }
        $current = intdiv($now, self::PERIOD);
        $matched = null;
        for ($step = $current - $window; $step <= $current + $window; $step++) {
            if ($lastStep !== null && $step <= $lastStep) {
                continue;
            }
            // evaluate every candidate to keep timing independent of which step matched
            if (hash_equals(self::codeAt($secret, $step), $code)) {
                $matched = $step;
            }
        }
        return $matched;
    }

    public static function provisioningUri(string $secretBase32, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $secretBase32,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }
}
