<?php

declare(strict_types=1);

namespace ServerWatch\Repositories;

use ServerWatch\Core\Clock;
use ServerWatch\Core\Db;
use ServerWatch\Core\Time;

/**
 * Append-only audit trail. There is intentionally no update/delete API. Metadata keys that look like secrets
 * are refused so credentials cannot be logged by accident.
 */
final class AuditLog
{
    private const FORBIDDEN_KEY = '/pass|token|csrf|secret|cookie|authorization|totp|mfa|hash|key/i';

    public function __construct(private readonly Db $db, private readonly Clock $clock)
    {
    }

    /**
     * @param array<string, scalar|null|list<scalar>> $metadata
     */
    public function record(
        ?int $organizationId,
        ?int $actorId,
        string $action,
        ?string $targetType,
        ?string $targetId,
        ?string $ip,
        array $metadata = [],
    ): void {
        foreach (array_keys($metadata) as $key) {
            if (preg_match(self::FORBIDDEN_KEY, (string) $key) === 1) {
                throw new \InvalidArgumentException("Audit metadata key '{$key}' looks like a secret");
            }
        }
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO audit_logs (organization_id, actor_id, action, target_type, target_id, ip_address,'
            . ' metadata_json, occurred_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $organizationId,
            $actorId,
            $action,
            $targetType,
            $targetId,
            $ip,
            $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            Time::db($this->clock->now()),
        ]);
    }
}
