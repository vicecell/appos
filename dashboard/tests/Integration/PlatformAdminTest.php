<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Integration;

use ServerWatch\Tests\Support\IntegrationTestCase;
use ServerWatch\Tests\Support\Session;

/**
 * Platform admins have NO implicit tenant access. Cross-tenant actions need an explicit, time-boxed,
 * revocable grant, and every use is audited.
 */
final class PlatformAdminTest extends IntegrationTestCase
{
    private int $org;
    private int $server;
    /** @var array{id: int, email: string, password: string, totp_secret: string|null} */
    private array $pa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = $this->fx->org('Target');
        $this->server = $this->fx->server($this->org, 'target-srv', 'target.example.test');
        $this->pa = $this->fx->user('root', platformAdmin: true, mfa: true);
    }

    private function session(): Session
    {
        return $this->loginWithMfa($this->pa);
    }

    public function testPlatformAdminWithoutGrantCannotSeeTenant(): void
    {
        $s = $this->session();

        self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$this->org}/servers", session: $s)->status);
        self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$this->org}/servers/{$this->server}", session: $s)->status);
        self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$this->org}/members", session: $s)->status);
        self::assertSame(
            404,
            $this->call('POST', "/api/v1/tenants/{$this->org}/servers", ['name' => 'x', 'hostname' => 'x.example.test'], $s)->status,
        );
    }

    public function testReadGrantAllowsReadsButNotWrites(): void
    {
        $this->fx->grant($this->pa['id'], $this->org, 'read');
        $s = $this->session();

        self::assertSame(200, $this->call('GET', "/api/v1/tenants/{$this->org}/servers", session: $s)->status);
        $write = $this->call('POST', "/api/v1/tenants/{$this->org}/servers", ['name' => 'x', 'hostname' => 'x.example.test'], $s);
        self::assertSame(403, $write->status);
        self::assertSame(1, $this->serverCount($this->org));
    }

    public function testManageGrantAllowsWritesAndEveryUseIsAudited(): void
    {
        $this->fx->grant($this->pa['id'], $this->org, 'manage');
        $s = $this->session();

        $res = $this->call('POST', "/api/v1/tenants/{$this->org}/servers", ['name' => 'ops', 'hostname' => 'ops.example.test'], $s);

        self::assertSame(201, $res->status);
        self::assertSame(1, $this->auditCount('platform.cross_tenant_access', $this->org));
        self::assertSame(1, $this->auditCount('server.create', $this->org));
    }

    public function testGrantForOneTenantDoesNotCoverAnother(): void
    {
        $other = $this->fx->org('Other');
        $this->fx->grant($this->pa['id'], $this->org, 'manage');
        $s = $this->session();

        self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$other}/servers", session: $s)->status);
    }

    public function testExpiredAndRevokedGrantsGrantNothing(): void
    {
        $this->fx->grant($this->pa['id'], $this->org, 'manage', ttlSeconds: 3600, revoked: true);
        $s = $this->session();
        self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$this->org}/servers", session: $s)->status);

        $this->pdo->exec('DELETE FROM platform_grants');
        $this->fx->grant($this->pa['id'], $this->org, 'read', ttlSeconds: 60);
        self::assertSame(200, $this->call('GET', "/api/v1/tenants/{$this->org}/servers", session: $s)->status);
        $this->clock->advance(61);
        // keep the session itself alive (idle timeout is 30 min), only the grant has lapsed
        self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$this->org}/servers", session: $s)->status);
    }

    public function testRegularUserCannotUseGrantTable(): void
    {
        $u = $this->fx->user('regular');
        $this->fx->grant($u['id'], $this->org, 'manage');   // grant row exists but user is not a platform admin
        $s = $this->login($u);

        self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$this->org}/servers", session: $s)->status);
    }

    public function testMembershipRoleWinsAndNoGrantAuditIsWrittenForOwnTenant(): void
    {
        $this->fx->member($this->org, $this->pa['id'], 'viewer');
        $s = $this->session();

        self::assertSame(200, $this->call('GET', "/api/v1/tenants/{$this->org}/servers", session: $s)->status);
        self::assertSame(0, $this->auditCount('platform.cross_tenant_access'));
        // as a plain viewer member, write is still denied even though platform admin
        self::assertSame(
            403,
            $this->call('POST', "/api/v1/tenants/{$this->org}/servers", ['name' => 'x', 'hostname' => 'x.example.test'], $s)->status,
        );
    }
}
