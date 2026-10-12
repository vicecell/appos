<?php

declare(strict_types=1);

namespace ServerWatch\Auth;

use InvalidArgumentException;
use PDOException;
use ServerWatch\Authz\GrantLevel;
use ServerWatch\Authz\Role;
use ServerWatch\Core\Clock;
use ServerWatch\Core\Db;
use ServerWatch\Core\Time;
use ServerWatch\Repositories\AuditLog;

/**
 * Operator-side provisioning (CLI only; there is no public API for this). Enforces the same invariants the
 * request path relies on: strong passwords, MFA for privileged accounts, time-boxed platform grants.
 */
final class Provisioner
{
    private const MAX_GRANT_SECONDS = 604800;

    public function __construct(
        private readonly Db $db,
        private readonly PasswordHasher $hasher,
        private readonly SecretBox $box,
        private readonly Clock $clock,
        private readonly AuditLog $audit,
    ) {
    }

    public function createOrganization(string $name, string $slug): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 190) {
            throw new InvalidArgumentException('Organization name must be 1-190 characters');
        }
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug) !== 1) {
            throw new InvalidArgumentException('Slug must be lowercase letters, digits and hyphens (max 63)');
        }
        try {
            $stmt = $this->db->pdo()->prepare('INSERT INTO organizations (name, slug, created_at) VALUES (?, ?, ?)');
            $stmt->execute([$name, $slug, Time::db($this->clock->now())]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new InvalidArgumentException('Organization slug already exists');
            }
            throw $e;
        }
        $id = (int) $this->db->pdo()->lastInsertId();
        $this->audit->record($id, null, 'organization.create', 'organization', (string) $id, null);
        return $id;
    }

    /**
     * @return array{id: int, totp_secret: string|null} totp_secret is base32, returned once for enrollment
     */
    public function createUser(
        string $name,
        string $email,
        string $password,
        bool $platformAdmin = false,
        bool $mfa = true,
    ): array {
        $name = trim($name);
        $email = AuthService::normalizeEmail($email);
        if ($name === '' || mb_strlen($name) > 190) {
            throw new InvalidArgumentException('Name must be 1-190 characters');
        }
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid e-mail address');
        }
        if (strlen($password) < 12 || strlen($password) > 1024) {
            throw new InvalidArgumentException('Password must be 12-1024 characters');
        }
        if ($platformAdmin && !$mfa) {
            throw new InvalidArgumentException('Platform admins must have MFA enabled');
        }

        $secret = $mfa ? Totp::generateSecret() : null;
        try {
            $stmt = $this->db->pdo()->prepare(
                'INSERT INTO users (name, email, password_hash, mfa_enabled, mfa_secret_enc, is_platform_admin, created_at)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $name,
                $email,
                $this->hasher->hash($password),
                $mfa ? 1 : 0,
                $secret === null ? null : $this->box->encrypt($secret),
                $platformAdmin ? 1 : 0,
                Time::db($this->clock->now()),
            ]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new InvalidArgumentException('E-mail address already registered');
            }
            throw $e;
        }
        $id = (int) $this->db->pdo()->lastInsertId();
        $this->audit->record(null, null, 'user.create', 'user', (string) $id, null, ['platform_admin' => $platformAdmin]);
        return ['id' => $id, 'totp_secret' => $secret === null ? null : Base32::encode($secret)];
    }

    public function addMember(int $organizationId, int $userId, Role $role): void
    {
        if ($role === Role::ClientAdmin) {
            $stmt = $this->db->pdo()->prepare('SELECT mfa_enabled FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            if ((int) $stmt->fetchColumn() !== 1) {
                throw new InvalidArgumentException('client_admin requires a user with MFA enabled');
            }
        }
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO organization_members (organization_id, user_id, role, created_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$organizationId, $userId, $role->value, Time::db($this->clock->now())]);
        $this->audit->record(
            $organizationId,
            null,
            'member.add',
            'user',
            (string) $userId,
            null,
            ['role' => $role->value],
        );
    }

    public function grantPlatformAccess(
        int $platformAdminId,
        int $organizationId,
        GrantLevel $level,
        string $reason,
        int $ttlSeconds,
        ?int $grantedBy,
    ): int {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new InvalidArgumentException('A reason of 1-255 characters is required');
        }
        if ($ttlSeconds < 1 || $ttlSeconds > self::MAX_GRANT_SECONDS) {
            throw new InvalidArgumentException('Grant lifetime must be between 1 second and 7 days');
        }
        $stmt = $this->db->pdo()->prepare('SELECT is_platform_admin FROM users WHERE id = ?');
        $stmt->execute([$platformAdminId]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new InvalidArgumentException('Grants can only be issued to platform admins');
        }

        $now = $this->clock->now();
        $ins = $this->db->pdo()->prepare(
            'INSERT INTO platform_grants (user_id, organization_id, access_level, reason, granted_by, created_at,'
            . ' expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $platformAdminId,
            $organizationId,
            $level->value,
            $reason,
            $grantedBy,
            Time::db($now),
            Time::db($now->modify("+{$ttlSeconds} seconds")),
        ]);
        $id = (int) $this->db->pdo()->lastInsertId();
        $this->audit->record(
            $organizationId,
            $grantedBy,
            'platform.grant.create',
            'platform_grant',
            (string) $id,
            null,
            ['level' => $level->value, 'ttl_seconds' => $ttlSeconds, 'subject_user_id' => $platformAdminId],
        );
        return $id;
    }

    public function revokeGrant(int $grantId, ?int $revokedBy): void
    {
        $find = $this->db->pdo()->prepare('SELECT organization_id FROM platform_grants WHERE id = ? AND revoked_at IS NULL');
        $find->execute([$grantId]);
        $org = $find->fetchColumn();
        if ($org === false) {
            throw new InvalidArgumentException('No such active grant');
        }
        $upd = $this->db->pdo()->prepare('UPDATE platform_grants SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL');
        $upd->execute([Time::db($this->clock->now()), $grantId]);
        $this->audit->record((int) $org, $revokedBy, 'platform.grant.revoke', 'platform_grant', (string) $grantId, null);
    }
}
