<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Integration;

use PDOException;
use ServerWatch\Tests\Support\IntegrationTestCase;

/**
 * Database-level guarantees, independent of application code.
 */
final class SchemaTest extends IntegrationTestCase
{
    public function testCompositeForeignKeyBlocksCrossTenantChildRows(): void
    {
        $a = $this->fx->org('A');
        $b = $this->fx->org('B');
        $serverB = $this->fx->server($b);
        $insert = $this->pdo->prepare(
            'INSERT INTO server_services (organization_id, server_id, service_name, last_state) VALUES (?, ?, ?, ?)'
        );

        // tenant A pointing at tenant B's server must be rejected by the database itself
        try {
            $insert->execute([$a, $serverB, 'nginx', 'active']);
            self::fail('cross-tenant reference was accepted');
        } catch (PDOException $e) {
            self::assertSame('23000', $e->getCode());
        }

        // while the correct tenant works
        $insert->execute([$b, $serverB, 'nginx', 'active']);
        $this->addToAssertionCount(1);
    }

    public function testUniqueConstraints(): void
    {
        $u = $this->fx->user('dup');
        $org = $this->fx->org('Dup');

        $this->expectUnique(fn () => $this->pdo->exec(
            "INSERT INTO users (name, email, password_hash, created_at) VALUES ('x', '" . strtoupper($u['email']) . "', 'h', NOW(3))"
        ));
        $this->expectUnique(fn () => $this->pdo->exec(
            "INSERT INTO organizations (name, slug, created_at) SELECT 'x', slug, NOW(3) FROM organizations WHERE id = {$org}"
        ));
        $this->fx->member($org, $u['id'], 'viewer');
        $this->expectUnique(fn () => $this->fx->member($org, $u['id'], 'operator'));
    }

    public function testInvalidEnumValuesAreRejectedInStrictMode(): void
    {
        $org = $this->fx->org('Enum');
        $u = $this->fx->user('enum');
        $this->expectException(PDOException::class);
        $this->fx->member($org, $u['id'], 'platform_admin');
    }

    private function expectUnique(callable $fn): void
    {
        try {
            $fn();
            self::fail('duplicate accepted');
        } catch (PDOException $e) {
            self::assertSame('23000', $e->getCode());
        }
    }
}
