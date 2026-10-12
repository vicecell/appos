<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ServerWatch\Auth\Base32;
use ServerWatch\Auth\Totp;

final class TotpTest extends TestCase
{
    private const SECRET = '12345678901234567890'; // RFC 6238 SHA1 test secret

    /**
     * @return array<string, array{int, string}>
     */
    public static function rfcVectors(): array
    {
        // RFC 6238 appendix B (SHA1); 6-digit codes are the last 6 digits of the published 8-digit values.
        return [
            't=59' => [59, '287082'],
            't=1111111109' => [1111111109, '081804'],
            't=1111111111' => [1111111111, '050471'],
            't=1234567890' => [1234567890, '005924'],
            't=2000000000' => [2000000000, '279037'],
        ];
    }

    #[DataProvider('rfcVectors')]
    public function testMatchesRfc6238Vectors(int $time, string $expected): void
    {
        self::assertSame($expected, Totp::codeAt(self::SECRET, intdiv($time, 30)));
    }

    public function testVerifyAcceptsCurrentAndAdjacentStepsOnly(): void
    {
        $now = 1111111111;
        $step = intdiv($now, 30);
        self::assertSame($step, Totp::verify(self::SECRET, Totp::codeAt(self::SECRET, $step), $now, null));
        self::assertSame($step - 1, Totp::verify(self::SECRET, Totp::codeAt(self::SECRET, $step - 1), $now, null));
        self::assertSame($step + 1, Totp::verify(self::SECRET, Totp::codeAt(self::SECRET, $step + 1), $now, null));
        self::assertNull(Totp::verify(self::SECRET, Totp::codeAt(self::SECRET, $step + 2), $now, null));
        self::assertNull(Totp::verify(self::SECRET, Totp::codeAt(self::SECRET, $step - 2), $now, null));
    }

    public function testVerifyRejectsReplayOfAlreadyUsedStep(): void
    {
        $now = 1111111111;
        $step = intdiv($now, 30);
        $code = Totp::codeAt(self::SECRET, $step);

        self::assertNull(Totp::verify(self::SECRET, $code, $now, $step));
        self::assertNull(Totp::verify(self::SECRET, $code, $now, $step + 1));
        self::assertSame($step, Totp::verify(self::SECRET, $code, $now, $step - 1));
    }

    public function testVerifyRejectsMalformedCodes(): void
    {
        foreach (['', '12345', '1234567', 'abcdef', '12 456', ' 287082'] as $bad) {
            self::assertNull(Totp::verify(self::SECRET, $bad, 59, null), "accepted '{$bad}'");
        }
    }

    public function testBase32RoundTripAndRfcVectors(): void
    {
        self::assertSame('MZXW6YTBOI', Base32::encode('foobar'));
        self::assertSame('foobar', Base32::decode('MZXW6YTBOI'));
        self::assertSame('foobar', Base32::decode('mzxw 6ytb oi=='));
        $raw = random_bytes(20);
        self::assertSame($raw, Base32::decode(Base32::encode($raw)));
    }

    public function testBase32RejectsInvalidAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Base32::decode('not*base32');
    }

    public function testGeneratedSecretsAreRandomAndProvisioningUriIsWellFormed(): void
    {
        $a = Totp::generateSecret();
        self::assertSame(20, strlen($a));
        self::assertNotSame($a, Totp::generateSecret());

        $uri = Totp::provisioningUri(Base32::encode($a), 'a.b@example.test', 'ServerWatch');
        self::assertStringStartsWith('otpauth://totp/ServerWatch:a.b%40example.test?', $uri);
        self::assertStringContainsString('secret=' . Base32::encode($a), $uri);
        self::assertStringContainsString('issuer=ServerWatch', $uri);
    }
}
