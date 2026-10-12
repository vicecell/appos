<?php

declare(strict_types=1);

namespace ServerWatch\Auth;

use ServerWatch\Core\Clock;
use ServerWatch\Core\HttpException;
use ServerWatch\Repositories\AuditLog;
use ServerWatch\Repositories\UserRepository;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly SecretBox $box,
        private readonly SessionStore $sessions,
        private readonly LoginThrottle $throttle,
        private readonly AuditLog $audit,
        private readonly Clock $clock,
    ) {
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * @return array{userId: int, token: string}
     * @throws HttpException 401 invalid_credentials | mfa_required, 403 mfa_setup_required, 429 rate_limited
     */
    public function login(string $email, string $password, ?string $totpCode, string $ip): array
    {
        $email = self::normalizeEmail($email);
        $identifier = LoginThrottle::identifier($email);

        $wait = $this->throttle->retryAfter($identifier, $ip);
        if ($wait !== null) {
            $this->audit->record(null, null, 'login.throttled', null, null, $ip);
            throw new HttpException(
                429,
                'rate_limited',
                'Too many failed attempts. Try again later.',
                ['Retry-After' => (string) $wait],
            );
        }

        $user = $this->users->findByEmail($email);
        // Always run a full password verification, even for unknown accounts (timing parity).
        $passwordOk = $this->hasher->verify($password, $user['password_hash'] ?? null);
        if ($user === null || !$passwordOk || $user['status'] !== 'active') {
            $this->fail($identifier, $ip, $user['id'] ?? null, 'bad_credentials');
        }

        $privileged = $this->users->isPrivileged($user['id'], $user['is_platform_admin']);
        if ($privileged && !$user['mfa_enabled']) {
            $this->audit->record(null, $user['id'], 'login.denied', null, null, $ip, ['reason' => 'mfa_setup_required']);
            throw new HttpException(403, 'mfa_setup_required', 'Multi-factor authentication must be set up for this account.');
        }

        if ($user['mfa_enabled']) {
            if ($totpCode === null) {
                throw new HttpException(401, 'mfa_required', 'A verification code is required.');
            }
            if ($user['mfa_secret_enc'] === null) {
                $this->fail($identifier, $ip, $user['id'], 'mfa_misconfigured');
            }
            $step = Totp::verify(
                $this->box->decrypt($user['mfa_secret_enc']),
                $totpCode,
                $this->clock->now()->getTimestamp(),
                $user['mfa_last_step'],
            );
            // claimTotpStep makes use of a step atomic, so two concurrent logins cannot share one code
            if ($step === null || !$this->users->claimTotpStep($user['id'], $step)) {
                $this->fail($identifier, $ip, $user['id'], 'mfa_invalid');
            }
        }

        $this->throttle->clearAccount($identifier);
        if ($this->hasher->needsRehash($user['password_hash'])) {
            $this->users->updatePasswordHash($user['id'], $this->hasher->hash($password));
        }

        $token = $this->sessions->create($user['id'], $ip);   // always a fresh token: no session fixation
        $this->audit->record(null, $user['id'], 'login.success', null, null, $ip);
        return ['userId' => $user['id'], 'token' => $token];
    }

    /**
     * @return never
     */
    private function fail(string $identifier, string $ip, ?int $userId, string $reason): never
    {
        $this->throttle->recordFailure($identifier, $ip);
        $this->audit->record(null, $userId, 'login.failure', null, null, $ip, ['reason' => $reason]);
        throw new HttpException(401, 'invalid_credentials', 'Invalid credentials.');
    }
}
