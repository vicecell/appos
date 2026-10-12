<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ServerWatch\Auth\PasswordHasher;
use ServerWatch\Auth\SecretBox;
use ServerWatch\Core\Config;

final class CryptoTest extends TestCase
{
    public function testPasswordHasherUsesArgon2idAndVerifies(): void
    {
        $h = new PasswordHasher(['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]);
        $hash = $h->hash('s3cret-password');

        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue($h->verify('s3cret-password', $hash));
        self::assertFalse($h->verify('wrong', $hash));
        self::assertNotSame($hash, $h->hash('s3cret-password'), 'salted');
    }

    public function testPasswordHasherVerifyAgainstNullStillBurnsAFullHashAndFails(): void
    {
        $h = new PasswordHasher(['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]);
        self::assertFalse($h->verify('anything', null));
    }

    public function testNeedsRehashWhenParametersChange(): void
    {
        $weak = new PasswordHasher(['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]);
        $strong = new PasswordHasher(['memory_cost' => 2048, 'time_cost' => 2, 'threads' => 1]);
        self::assertTrue($strong->needsRehash($weak->hash('x')));
        self::assertFalse($strong->needsRehash($strong->hash('x')));
    }

    public function testSecretBoxRoundTripAndTamperDetection(): void
    {
        $box = new SecretBox(str_repeat("\x01", 32));
        $enc = $box->encrypt('totp-secret-bytes');

        self::assertStringNotContainsString('totp-secret-bytes', $enc);
        self::assertSame('totp-secret-bytes', $box->decrypt($enc));
        self::assertNotSame($enc, $box->encrypt('totp-secret-bytes'), 'fresh nonce each time');

        $tampered = $enc;
        $tampered[strlen($tampered) - 1] = $tampered[strlen($tampered) - 1] ^ "\x01";
        $this->expectException(\RuntimeException::class);
        $box->decrypt($tampered);
    }

    public function testSecretBoxWithWrongKeyFails(): void
    {
        $enc = (new SecretBox(str_repeat("\x01", 32)))->encrypt('x');
        $this->expectException(\RuntimeException::class);
        (new SecretBox(str_repeat("\x02", 32)))->decrypt($enc);
    }

    public function testConfigRequiresA32ByteAppKey(): void
    {
        $env = static fn (array $e): \Closure => static fn (string $k): string|false => $e[$k] ?? false;

        $ok = Config::fromEnv($env(['APP_KEY' => base64_encode(str_repeat('k', 32))]));
        self::assertSame(str_repeat('k', 32), $ok->appKey);
        self::assertTrue($ok->cookieSecure, 'secure by default');

        foreach ([[], ['APP_KEY' => ''], ['APP_KEY' => 'not-base64!!'], ['APP_KEY' => base64_encode('short')]] as $bad) {
            try {
                Config::fromEnv($env($bad));
                self::fail('expected failure for ' . json_encode($bad));
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testConfigCookieNameUsesHostPrefixOnlyWhenSecure(): void
    {
        self::assertSame('__Host-sw_session', (new Config(str_repeat('k', 32), true))->cookieName());
        self::assertSame('sw_session', (new Config(str_repeat('k', 32), false))->cookieName());
    }
}
