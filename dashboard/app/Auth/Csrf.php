<?php

declare(strict_types=1);

namespace ServerWatch\Auth;

/**
 * Stateless synchronizer token: HMAC of the session token under the application key. It is bound to one session,
 * unguessable without the session token, and needs no storage.
 */
final class Csrf
{
    public function __construct(private readonly string $appKey)
    {
    }

    public function tokenFor(string $sessionToken): string
    {
        $mac = hash_hmac('sha256', 'csrf|' . $sessionToken, $this->appKey, true);
        return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }

    public function isValid(string $sessionToken, ?string $submitted): bool
    {
        return $submitted !== null && $submitted !== '' && hash_equals($this->tokenFor($sessionToken), $submitted);
    }
}
