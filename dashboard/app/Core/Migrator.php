<?php

declare(strict_types=1);

namespace ServerWatch\Core;

use PDO;
use RuntimeException;

/**
 * Applies and reverts versioned migrations. Never drops anything on its own:
 * rollback only runs a migration's own .down.sql, one step at a time.
 */
final class Migrator
{
    public function __construct(private readonly PDO $pdo, private readonly MigrationCatalog $catalog)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * @return list<string> ids applied by this call
     */
    public function up(): array
    {
        $this->ensureTable();
        $applied = $this->applied();
        $ran = [];
        foreach ($this->catalog->all() as $id => $files) {
            if (in_array($id, $applied, true)) {
                continue;
            }
            $this->pdo->exec($this->read($files['up']));
            $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (id, applied_at) VALUES (?, UTC_TIMESTAMP(3))');
            $stmt->execute([$id]);
            $ran[] = $id;
        }
        return $ran;
    }

    /**
     * Reverts the most recently applied migration.
     *
     * @return string|null id rolled back, or null when nothing was applied
     */
    public function down(): ?string
    {
        $this->ensureTable();
        $applied = $this->applied();
        $last = end($applied);
        if ($last === false) {
            return null;
        }
        $all = $this->catalog->all();
        if (!isset($all[$last])) {
            throw new RuntimeException("Applied migration {$last} is missing on disk");
        }
        $this->pdo->exec($this->read($all[$last]['down']));
        $stmt = $this->pdo->prepare('DELETE FROM schema_migrations WHERE id = ?');
        $stmt->execute([$last]);
        return $last;
    }

    /**
     * @return list<string>
     */
    public function applied(): array
    {
        $this->ensureTable();
        $rows = $this->pdo->query('SELECT id FROM schema_migrations ORDER BY id ASC');
        $ids = $rows === false ? [] : $rows->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_map('strval', $ids));
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . ' id VARCHAR(190) NOT NULL PRIMARY KEY,'
            . ' applied_at DATETIME(3) NOT NULL'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function read(string $path): string
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException("Cannot read migration {$path}");
        }
        return $sql;
    }
}
