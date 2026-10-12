<?php

declare(strict_types=1);

namespace ServerWatch\Repositories;

use DateTimeImmutable;
use ServerWatch\Core\Db;
use ServerWatch\Core\Time;

final class GrantRepository implements TenantScoped
{
    public function __construct(private readonly Db $db)
    {
    }

    /**
     * Strongest unexpired, unrevoked grant a platform admin holds on this organization (active orgs only).
     */
    public function activeLevel(int $organizationId, int $userId, DateTimeImmutable $now): ?string
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT g.access_level FROM platform_grants g
             JOIN users u ON u.id = g.user_id AND u.is_platform_admin = 1 AND u.status = 'active'
             JOIN organizations o ON o.id = g.organization_id AND o.status = 'active'
             WHERE g.organization_id = ? AND g.user_id = ? AND g.revoked_at IS NULL AND g.expires_at > ?
             ORDER BY g.access_level = 'manage' DESC LIMIT 1"
        );
        $stmt->execute([$organizationId, $userId, Time::db($now)]);
        $level = $stmt->fetchColumn();
        return $level === false ? null : (string) $level;
    }
}
