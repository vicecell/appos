<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Integration;

use ServerWatch\Auth\Provisioner;
use ServerWatch\Authz\GrantLevel;
use ServerWatch\Authz\Role;
use ServerWatch\Core\Db;
use ServerWatch\Repositories\AuditLog;
use ServerWatch\Tests\Support\IntegrationTestCase;

final class ProvisionerTest extends IntegrationTestCase
{
    private Provisioner $p;

    protected function setUp(): void
    {
        parent::setUp();
        $db = new Db(fn (): \PDO => $this->pdo);
        $this->p = new Provisioner($db, $this->hasher, $this->box, $this->clock, new AuditLog($db, $this->clock));
    }

    public function testProvisionedAdminCanLoginWithTotpAndReachOwnTenantOnly(): void
    {
        $orgA = $this->p->createOrganization('Alpha', 'alpha');
        $orgB = $this->p->createOrganization('Bravo', 'bravo');
        $user = $this->p->createUser('Ada', 'Ada@Example.Test', 'a-long-enough-passphrase');
        self::assertNotNull($user['totp_secret']);
        $this->p->addMember($orgA, $user['id'], Role::ClientAdmin);

        $creds = ['id' => $user['id'], 'email' => 'ada@example.test', 'password' => 'a-long-enough-passphrase', 'totp_secret' => $user['totp_secret']];
        $s = $this->loginWithMfa($creds);

        self::assertSame(200, $this->call('GET', "/api/v1/tenants/{$orgA}/servers", session: $s)->status);
        self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$orgB}/servers", session: $s)->status);
    }

    public function testRejectsWeakPasswordsBadSlugsAndDuplicates(): void
    {
        foreach (['short', 'elevenchars', str_repeat('a', 1025)] as $weak) {
            try {
                $this->p->createUser('X', 'x@example.test', $weak);
                self::fail("weak password accepted: {$weak}");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach (['Bad Slug', 'UPPER', '-x', 'x_y', ''] as $slug) {
            try {
                $this->p->createOrganization('Org', $slug);
                self::fail("bad slug accepted: {$slug}");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->p->createUser('X', 'dup@example.test', 'a-long-enough-passphrase');
        $this->expectException(\InvalidArgumentException::class);
        $this->p->createUser('Y', 'DUP@example.test', 'another-long-passphrase');
    }

    public function testPlatformAdminAndClientAdminMustHaveMfa(): void
    {
        $org = $this->p->createOrganization('Alpha', 'alpha');
        $noMfa = $this->p->createUser('NoMfa', 'nomfa@example.test', 'a-long-enough-passphrase', mfa: false);
        self::assertNull($noMfa['totp_secret']);

        try {
            $this->p->addMember($org, $noMfa['id'], Role::ClientAdmin);
            self::fail('client_admin without MFA accepted');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        $this->p->addMember($org, $noMfa['id'], Role::Viewer);   // non-privileged role is fine without MFA

        $this->expectException(\InvalidArgumentException::class);
        $this->p->createUser('Root', 'root@example.test', 'a-long-enough-passphrase', platformAdmin: true, mfa: false);
    }

    public function testGrantsAreTimeBoxedAuditedAndOnlyForPlatformAdmins(): void
    {
        $org = $this->p->createOrganization('Alpha', 'alpha');
        $root = $this->p->createUser('Root', 'root@example.test', 'a-long-enough-passphrase', platformAdmin: true);
        $regular = $this->p->createUser('Reg', 'reg@example.test', 'a-long-enough-passphrase');

        $grantId = $this->p->grantPlatformAccess($root['id'], $org, GrantLevel::Read, 'incident INC-42', 3600, $root['id']);
        self::assertGreaterThan(0, $grantId);
        self::assertSame(1, $this->auditCount('platform.grant.create', $org));

        foreach ([[$regular['id'], 3600], [$root['id'], 0], [$root['id'], 604801]] as [$uid, $ttl]) {
            try {
                $this->p->grantPlatformAccess($uid, $org, GrantLevel::Read, 'x', $ttl, $root['id']);
                self::fail("grant accepted for user {$uid} ttl {$ttl}");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->p->revokeGrant($grantId, $root['id']);
        self::assertSame(1, $this->auditCount('platform.grant.revoke', $org));
        $creds = ['id' => $root['id'], 'email' => 'root@example.test', 'password' => 'a-long-enough-passphrase', 'totp_secret' => $root['totp_secret']];
        $s = $this->loginWithMfa($creds);
        self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$org}/servers", session: $s)->status);
    }
}
