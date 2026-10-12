<?php

declare(strict_types=1);

namespace ServerWatch\Core;

use RuntimeException;

final class Config
{
    public function __construct(
        public readonly string $appKey,
        public readonly bool $cookieSecure = true,
        public readonly int $sessionIdleSeconds = 1800,
        public readonly int $sessionAbsoluteSeconds = 43200,
        public readonly int $loginWindowSeconds = 900,
        public readonly int $loginMaxFailuresPerAccount = 5,
        public readonly int $loginMaxFailuresPerIp = 20,
    ) {
        if (strlen($appKey) !== 32) {
            throw new RuntimeException('APP_KEY must decode to exactly 32 bytes');
        }
    }

    /**
     * @param \Closure(string): (string|false) $getenv
     */
    public static function fromEnv(\Closure $getenv): self
    {
        $encoded = $getenv('APP_KEY');
        if ($encoded === false || $encoded === '') {
            throw new RuntimeException('APP_KEY is required (base64 of 32 random bytes)');
        }
        $key = base64_decode($encoded, true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('APP_KEY must be valid base64 of exactly 32 bytes');
        }
        $secure = $getenv('COOKIE_SECURE');
        return new self($key, $secure === false || strtolower($secure) !== 'false');
    }

    public function cookieName(): string
    {
        return $this->cookieSecure ? '__Host-sw_session' : 'sw_session';
    }

    public function sessionCookie(string $token, int $maxAge): string
    {
        return sprintf(
            '%s=%s; Path=/; HttpOnly; SameSite=Lax; Max-Age=%d%s',
            $this->cookieName(),
            $token,
            $maxAge,
            $this->cookieSecure ? '; Secure' : '',
        );
    }
}
