<?php

declare(strict_types=1);

namespace ServerWatch\Auth;

final class PasswordHasher
{
    private ?string $dummyHash = null;

    /**
     * @param array{memory_cost?: int, time_cost?: int, threads?: int} $options empty = PHP defaults for Argon2id
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, $this->options);
    }

    /**
     * A null hash (unknown account) still performs a full verification so response time does not reveal
     * whether the account exists.
     */
    public function verify(string $password, ?string $hash): bool
    {
        if ($hash === null) {
            $this->dummyHash ??= $this->hash('serverwatch-dummy-password');
            password_verify($password, $this->dummyHash);
            return false;
        }
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options);
    }
}
