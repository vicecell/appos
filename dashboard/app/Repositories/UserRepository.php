<?php

declare(strict_types=1);

namespace ServerWatch\Repositories;

use ServerWatch\Core\Db;

/**
 * Users are global (an account may belong to several tenants), so this repository is NOT tenant-scoped.
 * Never expose its raw rows over the API.
 */
final class UserRepository
{
    public function __construct(private readonly Db $db)
    {
    }

    /**
     * @return array{id: int, name: string, email: string, password_hash: string, mfa_enabled: bool,
     *     mfa_secret_enc: string|null, mfa_last_step: int|null, is_platform_admin: bool, status: string}|null
     */
    public function findByEmail(string $normalizedEmail): ?array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT id, name, email, password_hash, mfa_enabled, mfa_secret_enc, mfa_last_step, is_platform_admin, status'
            . ' FROM users WHERE email = ?'
        );
        $stmt->execute([$normalizedEmail]);
        $r = $stmt->fetch();
        if (!is_array($r)) {
            return null;
        }
        return [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'email' => (string) $r['email'],
            'password_hash' => (string) $r['password_hash'],
            'mfa_enabled' => (bool) $r['mfa_enabled'],
            'mfa_secret_enc' => $r['mfa_secret_enc'] === null ? null : (string) $r['mfa_secret_enc'],
            'mfa_last_step' => $r['mfa_last_step'] === null ? null : (int) $r['mfa_last_step'],
            'is_platform_admin' => (bool) $r['is_platform_admin'],
            'status' => (string) $r['status'],
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, is_platform_admin: bool}|null
     */
    public function publicProfile(int $userId): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT id, name, email, is_platform_admin FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $r = $stmt->fetch();
        if (!is_array($r)) {
            return null;
        }
        return [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'email' => (string) $r['email'],
            'is_platform_admin' => (bool) $r['is_platform_admin'],
        ];
    }

    /**
     * Platform admins and any active client_admin must use MFA.
     */
    public function isPrivileged(int $userId, bool $isPlatformAdmin): bool
    {
        if ($isPlatformAdmin) {
            return true;
        }
        $stmt = $this->db->pdo()->prepare(
            "SELECT 1 FROM organization_members WHERE user_id = ? AND role = 'client_admin' AND status = 'active' LIMIT 1"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Atomically records a TOTP step as used. False means it was already used (replay) or a newer one was.
     */
    public function claimTotpStep(int $userId, int $step): bool
    {
        $stmt = $this->db->pdo()->prepare(
            'UPDATE users SET mfa_last_step = ? WHERE id = ? AND (mfa_last_step IS NULL OR mfa_last_step < ?)'
        );
        $stmt->execute([$step, $userId, $step]);
        return $stmt->rowCount() === 1;
    }

    public function updatePasswordHash(int $userId, string $hash): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $userId]);
    }

    /**
     * Active memberships in active organizations, for the "who am I" response.
     *
     * @return list<array{organization: array{id: int, name: string, slug: string}, role: string}>
     */
    public function activeMemberships(int $userId): array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT o.id, o.name, o.slug, m.role FROM organization_members m
             JOIN organizations o ON o.id = m.organization_id
             WHERE m.user_id = ? AND m.status = 'active' AND o.status = 'active' ORDER BY o.id"
        );
        $stmt->execute([$userId]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = [
                'organization' => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'slug' => (string) $r['slug']],
                'role' => (string) $r['role'],
            ];
        }
        return $out;
    }
}
