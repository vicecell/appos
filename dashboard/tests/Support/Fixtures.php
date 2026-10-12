<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Support;

use PDO;
use ServerWatch\Auth\Base32;
use ServerWatch\Auth\PasswordHasher;
use ServerWatch\Auth\SecretBox;
use ServerWatch\Auth\Totp;
use ServerWatch\Core\Clock;
use ServerWatch\Core\Time;

/**
 * Direct-SQL test data builders. They intentionally bypass the application layer.
 */
final class Fixtures
{
    private int $seq = 0;

    public function __construct(
        private readonly PDO $pdo,
        private readonly PasswordHasher $hasher,
        private readonly SecretBox $box,
        private readonly Clock $clock,
    ) {
    }

    private function ts(): string
    {
        return Time::db($this->clock->now());
    }

    public function org(string $name = 'Org', string $status = 'active'): int
    {
        $this->seq++;
        $slug = strtolower($name) . '-' . $this->seq . '-' . bin2hex(random_bytes(3));
        $stmt = $this->pdo->prepare('INSERT INTO organizations (name, slug, status, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $slug, $status, $this->ts()]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array{id: int, email: string, password: string, totp_secret: string|null}
     */
    public function user(
        string $label = 'user',
        bool $platformAdmin = false,
        bool $mfa = false,
        string $status = 'active',
    ): array {
        $this->seq++;
        $email = $label . $this->seq . '@example.test';
        $password = 'correct horse battery ' . $this->seq;
        $secret = $mfa ? Totp::generateSecret() : null;
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash, mfa_enabled, mfa_secret_enc, is_platform_admin, status,'
            . ' created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            ucfirst($label),
            $email,
            $this->hasher->hash($password),
            $mfa ? 1 : 0,
            $secret === null ? null : $this->box->encrypt($secret),
            $platformAdmin ? 1 : 0,
            $status,
            $this->ts(),
        ]);
        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'email' => $email,
            'password' => $password,
            'totp_secret' => $secret === null ? null : Base32::encode($secret),
        ];
    }

    public function member(int $orgId, int $userId, string $role, string $status = 'active'): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO organization_members (organization_id, user_id, role, status, created_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orgId, $userId, $role, $status, $this->ts()]);
    }

    public function server(int $orgId, string $name = 'srv', ?string $hostname = null): int
    {
        $this->seq++;
        $now = $this->ts();
        $stmt = $this->pdo->prepare(
            'INSERT INTO servers (organization_id, name, hostname, created_at, updated_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orgId, $name, $hostname ?? ('host' . $this->seq . '.example.test'), $now, $now]);
        return (int) $this->pdo->lastInsertId();
    }

    public function grant(int $userId, int $orgId, string $level, int $ttlSeconds = 3600, bool $revoked = false): void
    {
        $now = $this->clock->now();
        $stmt = $this->pdo->prepare(
            'INSERT INTO platform_grants (user_id, organization_id, access_level, reason, created_at, expires_at,'
            . ' revoked_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $orgId,
            $level,
            'test grant',
            Time::db($now),
            Time::db($now->modify(sprintf('%+d seconds', $ttlSeconds))),
            $revoked ? Time::db($now) : null,
        ]);
    }

    /**
     * Current valid TOTP code for a base32 secret (offset in 30s steps).
     */
    public function totpNow(string $secretBase32, int $offsetSteps = 0): string
    {
        $step = intdiv($this->clock->now()->getTimestamp(), 30) + $offsetSteps;
        return Totp::codeAt(Base32::decode($secretBase32), $step);
    }
}
