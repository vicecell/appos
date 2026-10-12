<?php

declare(strict_types=1);

namespace ServerWatch\Auth;

use RuntimeException;

/**
 * Authenticated encryption (XSalsa20-Poly1305) for secrets stored at rest, e.g. TOTP seeds.
 * The key is derived from APP_KEY with domain separation. Format: nonce || ciphertext.
 */
final class SecretBox
{
    private string $key;

    public function __construct(string $appKey)
    {
        if (strlen($appKey) !== 32) {
            throw new RuntimeException('SecretBox needs a 32-byte application key');
        }
        $this->key = hash_hmac('sha256', 'serverwatch/secretbox/v1', $appKey, true);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return $nonce . sodium_crypto_secretbox($plain, $nonce, $this->key);
    }

    public function decrypt(string $blob): string
    {
        if (strlen($blob) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new RuntimeException('Ciphertext is malformed');
        }
        $nonce = substr($blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if ($plain === false) {
            throw new RuntimeException('Ciphertext failed authentication');
        }
        return $plain;
    }
}
