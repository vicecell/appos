<?php

declare(strict_types=1);

namespace ServerWatch\Auth;

use ServerWatch\Core\Clock;
use ServerWatch\Core\Config;
use ServerWatch\Core\Db;
use ServerWatch\Core\Time;

/**
 * Server-side sessions. The cookie carries a random 256-bit token; only its SHA-256 is stored, so a database
 * leak does not yield usable sessions. Sessions can be revoked and have idle and absolute lifetimes.
 */
final class SessionStore
{
    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly Config $config,
    ) {
    }

    public static function isWellFormed(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1;
    }

    /**
     * @return string the new session token (shown to the client exactly once, as a cookie)
     */
    public function create(int $userId, string $ip): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = $this->clock->now();
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO sessions (id, user_id, created_at, last_seen_at, expires_at, ip_address) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            hash('sha256', $token),
            $userId,
            Time::db($now),
            Time::db($now),
            Time::db($now->modify('+' . $this->config->sessionAbsoluteSeconds . ' seconds')),
            $ip,
        ]);
        return $token;
    }

    public function resolve(string $token): ?Principal
    {
        if (!self::isWellFormed($token)) {
            return null;
        }
        $id = hash('sha256', $token);
        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare(
            "SELECT s.last_seen_at, s.expires_at, u.id AS user_id, u.is_platform_admin
             FROM sessions s JOIN users u ON u.id = s.user_id
             WHERE s.id = ? AND u.status = 'active'"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        $now = $this->clock->now();
        $idleDeadline = Time::parse((string) $row['last_seen_at'])->modify('+' . $this->config->sessionIdleSeconds . ' seconds');
        if ($now >= Time::parse((string) $row['expires_at']) || $now >= $idleDeadline) {
            $this->revoke($token);
            return null;
        }

        $touch = $pdo->prepare('UPDATE sessions SET last_seen_at = ? WHERE id = ?');
        $touch->execute([Time::db($now), $id]);

        return new Principal((int) $row['user_id'], (bool) $row['is_platform_admin']);
    }

    public function revoke(string $token): void
    {
        $stmt = $this->db->pdo()->prepare('DELETE FROM sessions WHERE id = ?');
        $stmt->execute([hash('sha256', $token)]);
    }
}
