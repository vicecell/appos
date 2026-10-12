<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ServerWatch\Core\MigrationCatalog;

final class MigrationCatalogTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sw_mig_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->dir);
    }

    private function touchFile(string $name): void
    {
        file_put_contents($this->dir . DIRECTORY_SEPARATOR . $name, 'SELECT 1;');
    }

    public function testListsReversibleMigrationsInOrder(): void
    {
        $this->touchFile('0002_b.up.sql');
        $this->touchFile('0002_b.down.sql');
        $this->touchFile('0001_a.up.sql');
        $this->touchFile('0001_a.down.sql');

        self::assertSame(['0001_a', '0002_b'], array_keys((new MigrationCatalog($this->dir))->all()));
    }

    public function testRejectsMigrationWithoutDown(): void
    {
        $this->touchFile('0001_a.up.sql');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not reversible');
        (new MigrationCatalog($this->dir))->all();
    }

    public function testRejectsBadFilename(): void
    {
        $this->touchFile('create_users.sql');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid migration filename');
        (new MigrationCatalog($this->dir))->all();
    }

    public function testRejectsDuplicateVersion(): void
    {
        foreach (['0001_a.up.sql', '0001_a.down.sql', '0001_b.up.sql', '0001_b.down.sql'] as $f) {
            $this->touchFile($f);
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate migration version 0001');
        (new MigrationCatalog($this->dir))->all();
    }

    public function testMissingDirectoryFails(): void
    {
        $this->expectException(RuntimeException::class);
        (new MigrationCatalog($this->dir . '_missing'))->all();
    }
}
