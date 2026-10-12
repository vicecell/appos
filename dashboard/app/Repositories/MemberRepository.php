<?php

declare(strict_types=1);

namespace ServerWatch\Repositories;

use ServerWatch\Core\Db;

final class MemberRepository implements TenantScoped
{
    public function __construct(private readonly Db $db)
    {
    }

    /**
     * Role of an ACTIVE member in an ACTIVE organization, otherwise null.
     */
    public function activeRole(int $organizationId, int $userId): ?string
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT m.role FROM organization_members m JOIN organizations o ON o.id = m.organization_id
             WHERE m.organization_id = ? AND m.user_id = ? AND m.status = 'active' AND o.status = 'active'"
        );
        $stmt->execute([$organizationId, $userId]);
        $role = $stmt->fetchColumn();
        return $role === false ? null : (string) $role;
    }

    /**
     * @return list<array{user_id: int, name: string, email: string, role: string, status: string}>
     */
    public function list(int $organizationId): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT u.id AS user_id, u.name, u.email, m.role, m.status
             FROM organization_members m JOIN users u ON u.id = m.user_id
             WHERE m.organization_id = ? ORDER BY m.id LIMIT 500'
        );
        $stmt->execute([$organizationId]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = [
                'user_id' => (int) $r['user_id'],
                'name' => (string) $r['name'],
                'email' => (string) $r['email'],
                'role' => (string) $r['role'],
                'status' => (string) $r['status'],
            ];
        }
        return $out;
    }
}
