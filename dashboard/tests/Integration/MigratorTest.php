<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use ServerWatch\Core\MigrationCatalog;
use ServerWatch\Core\Migrator;
use ServerWatch\Tests\Support\IntegrationTestCase;

/**
 * Applies the REAL migrations to the throwaway *_test database: up, full rollback one step at a time, up again.
 * DDL commits implicitly, so this test manages its own state and always leaves the schema fully migrated.
 */
final class MigratorTest extends TestCase
{
    private PDO $pdo;
    private MigrationCatalog $catalog;

    protected function setUp(): void
    {
        $this->pdo = IntegrationTestCase::connectTestDb();
        $this->catalog = new MigrationCatalog(__DIR__ . '/../../database/migrations');
        IntegrationTestCase::dropAllTables($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            IntegrationTestCase::dropAllTables($this->pdo);
            (new Migrator($this->pdo, $this->catalog))->up();
        }
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        $rows = $this->pdo->query('SHOW TABLES');
        self::assertNotFalse($rows);
        $names = array_map('strval', $rows->fetchAll(PDO::FETCH_COLUMN));
        sort($names);
        return $names;
    }

    public function testRealMigrationsApplyRollBackStepwiseAndReapply(): void
    {
        $migrator = new Migrator($this->pdo, $this->catalog);
        $ids = array_keys($this->catalog->all());
        self::assertGreaterThanOrEqual(7, count($ids));

        self::assertSame($ids, $migrator->up());
        self::assertSame([], $migrator->up(), 'idempotent');
        $full = $this->tables();
        foreach (['organizations', 'users', 'organization_members', 'sessions', 'servers', 'audit_logs', 'platform_grants'] as $t) {
            self::assertContains($t, $full);
        }

        foreach (array_reverse($ids) as $id) {
            self::assertSame($id, $migrator->down());
        }
        self::assertNull($migrator->down());
        self::assertSame(['schema_migrations'], $this->tables(), 'every migration must be fully reversible');

        self::assertSame($ids, $migrator->up());
        self::assertSame($full, $this->tables());
    }

    public function testEveryTenantScopedTableHasOrganizationIdAndAnIndexLeadingWithIt(): void
    {
        (new Migrator($this->pdo, $this->catalog))->up();
        $exempt = ['organizations', 'users', 'sessions', 'login_attempts', 'schema_migrations', 'platform_grants'];

        foreach ($this->tables() as $table) {
            if (in_array($table, $exempt, true)) {
                continue;
            }
            $cols = $this->pdo->query("SHOW COLUMNS FROM `{$table}`");
            self::assertNotFalse($cols);
            self::assertContains('organization_id', $cols->fetchAll(PDO::FETCH_COLUMN), "{$table} lacks organization_id");

            $idx = $this->pdo->query("SHOW INDEX FROM `{$table}` WHERE Seq_in_index = 1 AND Column_name = 'organization_id'");
            self::assertNotFalse($idx);
            self::assertNotEmpty($idx->fetchAll(), "{$table} has no index leading with organization_id");
        }
    }
}
