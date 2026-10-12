<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use ServerWatch\Core\MigrationCatalog;
use ServerWatch\Core\Migrator;

/**
 * Needs a MySQL 8 instance (DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASSWORD); skipped otherwise.
 * Uses a throwaway table name prefix and cleans up after itself.
 */
final class MigratorTest extends TestCase
{
    private PDO $pdo;
    private string $dir;

    protected function setUp(): void
    {
        $name = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASSWORD');
        if ($name === false || $user === false || $pass === false) {
            self::markTestSkipped('DB_* environment not set');
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_PORT') ?: '3306',
            $name,
        );
        $this->pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('DROP TABLE IF EXISTS schema_migrations, it_probe');

        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sw_it_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents(
            $this->dir . '/0001_probe.up.sql',
            'CREATE TABLE it_probe (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB;',
        );
        file_put_contents($this->dir . '/0001_probe.down.sql', 'DROP TABLE it_probe;');
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP TABLE IF EXISTS schema_migrations, it_probe');
        }
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testUpIsIdempotentAndDownReverts(): void
    {
        $migrator = new Migrator($this->pdo, new MigrationCatalog($this->dir));

        self::assertSame(['0001_probe'], $migrator->up());
        self::assertSame([], $migrator->up(), 'second run must be a no-op');
        self::assertSame(['0001_probe'], $migrator->applied());
        self::assertNotFalse($this->pdo->query('SELECT 1 FROM it_probe'));

        self::assertSame('0001_probe', $migrator->down());
        self::assertNull($migrator->down());
        self::assertSame([], $migrator->applied());
        $tables = $this->pdo->query("SHOW TABLES LIKE 'it_probe'");
        self::assertNotFalse($tables);
        self::assertSame(0, $tables->rowCount());
    }
}
