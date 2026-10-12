<?php

declare(strict_types=1);

namespace ServerWatch\Auth;

/**
 * RFC 4648 base32 (as used by authenticator apps). Encoding is unpadded.
 */
final class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encode(string $raw): string
    {
        $bits = '';
        foreach (str_split($raw) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[(int) bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function decode(string $encoded): string
    {
        $clean = strtoupper(str_replace([' ', '='], '', $encoded));
        $bits = '';
        foreach (str_split($clean) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                throw new \InvalidArgumentException('Invalid base32 input');
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr((int) bindec($byte));
            }
        }
        return $out;
    }
}
