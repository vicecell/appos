<?php

declare(strict_types=1);

namespace ServerWatch\Auth;

use ServerWatch\Core\Clock;
use ServerWatch\Core\Config;
use ServerWatch\Core\Db;
use ServerWatch\Core\Time;

/**
 * Failure-based limiter keyed by account (hashed e-mail) and by client IP. It counts failures only, so a
 * legitimate user is never locked out by their own successful logins.
 */
final class LoginThrottle
{
    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly Config $config,
    ) {
    }

    public static function identifier(string $normalizedEmail): string
    {
        return hash('sha256', $normalizedEmail);
    }

    /**
     * @return int|null seconds until retry is allowed, or null when the attempt may proceed
     */
    public function retryAfter(string $identifierHash, string $ip): ?int
    {
        $waits = array_filter([
            $this->wait('identifier_hash', $identifierHash, $this->config->loginMaxFailuresPerAccount),
            $this->wait('ip_address', $ip, $this->config->loginMaxFailuresPerIp),
        ], static fn (?int $w): bool => $w !== null);

        return $waits === [] ? null : max($waits);
    }

    public function recordFailure(string $identifierHash, string $ip): void
    {
        $pdo = $this->db->pdo();
        $now = $this->clock->now();
        $stmt = $pdo->prepare('INSERT INTO login_attempts (identifier_hash, ip_address, attempted_at) VALUES (?, ?, ?)');
        $stmt->execute([$identifierHash, $ip, Time::db($now)]);

        $cutoff = $now->modify('-' . ($this->config->loginWindowSeconds * 2) . ' seconds');
        $prune = $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?');
        $prune->execute([Time::db($cutoff)]);
    }

    public function clearAccount(string $identifierHash): void
    {
        $stmt = $this->db->pdo()->prepare('DELETE FROM login_attempts WHERE identifier_hash = ?');
        $stmt->execute([$identifierHash]);
    }

    /**
     * @param 'identifier_hash'|'ip_address' $column fixed set, never client input
     */
    private function wait(string $column, string $value, int $max): ?int
    {
        $now = $this->clock->now();
        $since = $now->modify('-' . $this->config->loginWindowSeconds . ' seconds');
        $stmt = $this->db->pdo()->prepare(
            "SELECT COUNT(*) AS n, MIN(attempted_at) AS oldest FROM login_attempts WHERE {$column} = ? AND attempted_at > ?"
        );
        $stmt->execute([$value, Time::db($since)]);
        $row = $stmt->fetch();
        if (!is_array($row) || (int) $row['n'] < $max || $row['oldest'] === null) {
            return null;
        }
        $free = Time::parse((string) $row['oldest'])->modify('+' . $this->config->loginWindowSeconds . ' seconds');
        return max(1, $free->getTimestamp() - $now->getTimestamp());
    }
}
