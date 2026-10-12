<?php

declare(strict_types=1);

namespace ServerWatch\Repositories;

use PDOException;
use ServerWatch\Core\Clock;
use ServerWatch\Core\Db;
use ServerWatch\Core\HttpException;
use ServerWatch\Core\Time;

/**
 * Every query is constrained by organization_id; there is no method that takes only a server id.
 */
final class ServerRepository implements TenantScoped
{
    private const COLUMNS = 'id, name, hostname, status, last_seen_at, created_at, updated_at';

    public function __construct(private readonly Db $db, private readonly Clock $clock)
    {
    }

    /**
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function list(int $organizationId, int $limit, int $afterId = 0): array
    {
        $limit = max(1, min(100, $limit));
        $fetch = $limit + 1;
        $stmt = $this->db->pdo()->prepare(
            'SELECT ' . self::COLUMNS . " FROM servers WHERE organization_id = ? AND id > ? ORDER BY id ASC LIMIT {$fetch}"
        );
        $stmt->execute([$organizationId, $afterId]);
        $rows = $stmt->fetchAll();

        $more = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $items = array_values(array_map(fn (array $r): array => $this->present($r), $rows));
        $last = $items === [] ? null : $items[array_key_last($items)];

        return ['items' => $items, 'next_cursor' => $more && $last !== null ? (string) $last['id'] : null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $organizationId, int $id): ?array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM servers WHERE organization_id = ? AND id = ?'
        );
        $stmt->execute([$organizationId, $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $this->present($row) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(int $organizationId, string $name, string $hostname): array
    {
        $now = Time::db($this->clock->now());
        try {
            $stmt = $this->db->pdo()->prepare(
                'INSERT INTO servers (organization_id, name, hostname, created_at, updated_at) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$organizationId, $name, $hostname, $now, $now]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new HttpException(409, 'conflict', 'A server with this hostname already exists.');
            }
            throw $e;
        }
        $created = $this->find($organizationId, (int) $this->db->pdo()->lastInsertId());
        return $created ?? throw new \LogicException('Inserted server could not be read back');
    }

    /**
     * @return array<string, mixed>|null null when the server does not exist in this organization
     */
    public function rename(int $organizationId, int $id, string $name): ?array
    {
        if ($this->find($organizationId, $id) === null) {
            return null;
        }
        $stmt = $this->db->pdo()->prepare(
            'UPDATE servers SET name = ?, updated_at = ? WHERE organization_id = ? AND id = ?'
        );
        $stmt->execute([$name, Time::db($this->clock->now()), $organizationId, $id]);
        return $this->find($organizationId, $id);
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function present(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'hostname' => (string) $r['hostname'],
            'status' => (string) $r['status'],
            'last_seen_at' => $r['last_seen_at'] === null ? null : Time::iso((string) $r['last_seen_at']),
            'created_at' => Time::iso((string) $r['created_at']),
            'updated_at' => Time::iso((string) $r['updated_at']),
        ];
    }
}
