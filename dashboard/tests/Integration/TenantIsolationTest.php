<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Integration;

use ServerWatch\Tests\Support\IntegrationTestCase;
use ServerWatch\Tests\Support\Session;

/**
 * SW-002 acceptance gate: a user from tenant A cannot read or modify tenant B resources.
 * Two independent tenants, each with its own admin/operator/viewer identities.
 */
final class TenantIsolationTest extends IntegrationTestCase
{
    private int $orgA;
    private int $orgB;
    private int $serverA;
    private int $serverB;
    private Session $adminA;
    private Session $viewerA;
    private Session $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orgA = $this->fx->org('Alpha');
        $this->orgB = $this->fx->org('Bravo');
        $this->serverA = $this->fx->server($this->orgA, 'a-web', 'a-web.example.test');
        $this->serverB = $this->fx->server($this->orgB, 'b-secret-db', 'b-db.example.test');

        $adminA = $this->fx->user('admina', mfa: true);
        $viewerA = $this->fx->user('viewera');
        $adminB = $this->fx->user('adminb', mfa: true);
        $this->fx->member($this->orgA, $adminA['id'], 'client_admin');
        $this->fx->member($this->orgA, $viewerA['id'], 'viewer');
        $this->fx->member($this->orgB, $adminB['id'], 'client_admin');

        $this->adminA = $this->loginWithMfa($adminA);
        $this->viewerA = $this->login($viewerA);
        $this->adminB = $this->loginWithMfa($adminB);
    }

    public function testListOnlyContainsOwnTenantsServers(): void
    {
        $res = $this->call('GET', "/api/v1/tenants/{$this->orgA}/servers", session: $this->adminA);

        self::assertSame(200, $res->status);
        $names = array_column($this->json($res)['data'], 'name');
        self::assertSame(['a-web'], $names);
        self::assertStringNotContainsString('b-secret-db', $res->body);
    }

    public function testCannotListAnotherTenantsServers(): void
    {
        $res = $this->call('GET', "/api/v1/tenants/{$this->orgB}/servers", session: $this->adminA);

        self::assertSame(404, $res->status);
        self::assertStringNotContainsString('b-secret-db', $res->body);
        self::assertSame('not_found', $this->json($res)['error']['code']);
    }

    public function testIsolationIsSymmetric(): void
    {
        $own = $this->call('GET', "/api/v1/tenants/{$this->orgB}/servers", session: $this->adminB);
        self::assertSame(['b-secret-db'], array_column($this->json($own)['data'], 'name'));

        self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$this->orgA}/servers", session: $this->adminB)->status);
        $res = $this->call('GET', "/api/v1/tenants/{$this->orgB}/servers/{$this->serverA}", session: $this->adminB);
        self::assertSame(404, $res->status);
        self::assertStringNotContainsString('a-web', $res->body);
    }

    public function testCannotReadAnotherTenantsServerById(): void
    {
        // Own path, foreign id (IDOR)
        $res = $this->call('GET', "/api/v1/tenants/{$this->orgA}/servers/{$this->serverB}", session: $this->adminA);
        self::assertSame(404, $res->status);
        self::assertStringNotContainsString('b-secret-db', $res->body);

        // Foreign path, foreign id
        $res = $this->call('GET', "/api/v1/tenants/{$this->orgB}/servers/{$this->serverB}", session: $this->adminA);
        self::assertSame(404, $res->status);
    }

    public function testNonexistentAndForeignResourcesAreIndistinguishable(): void
    {
        $foreign = $this->call('GET', "/api/v1/tenants/{$this->orgA}/servers/{$this->serverB}", session: $this->adminA);
        $missing = $this->call('GET', "/api/v1/tenants/{$this->orgA}/servers/99999999", session: $this->adminA);
        $foreignTenant = $this->call('GET', "/api/v1/tenants/{$this->orgB}/servers", session: $this->adminA);
        $missingTenant = $this->call('GET', '/api/v1/tenants/99999999/servers', session: $this->adminA);

        self::assertSame($missing->status, $foreign->status);
        self::assertSame($missing->body, $foreign->body);
        self::assertSame($missingTenant->status, $foreignTenant->status);
        self::assertSame($missingTenant->body, $foreignTenant->body);
    }

    public function testCannotModifyAnotherTenantsServer(): void
    {
        foreach ([$this->orgA, $this->orgB] as $pathOrg) {
            $res = $this->call(
                'PATCH',
                "/api/v1/tenants/{$pathOrg}/servers/{$this->serverB}",
                ['name' => 'pwned'],
                $this->adminA,
            );
            self::assertSame(404, $res->status, "path org {$pathOrg}");
        }
        $name = $this->queryValue("SELECT name FROM servers WHERE id = {$this->serverB}");
        self::assertSame('b-secret-db', $name);
    }

    public function testCannotCreateServerInAnotherTenant(): void
    {
        $before = $this->serverCount($this->orgB);

        $res = $this->call(
            'POST',
            "/api/v1/tenants/{$this->orgB}/servers",
            ['name' => 'implant', 'hostname' => 'implant.example.test'],
            $this->adminA,
        );

        self::assertSame(404, $res->status);
        self::assertSame($before, $this->serverCount($this->orgB));
    }

    public function testTenantIdInBodyCannotOverridePathScope(): void
    {
        $before = $this->serverCount($this->orgB);

        foreach (['organization_id', 'tenant_id', 'organizationId'] as $field) {
            $res = $this->call(
                'POST',
                "/api/v1/tenants/{$this->orgA}/servers",
                ['name' => 'sneaky', 'hostname' => 'sneaky.example.test', $field => $this->orgB],
                $this->adminA,
            );
            self::assertSame(422, $res->status, $field);
        }
        self::assertSame($before, $this->serverCount($this->orgB));
        self::assertSame(1, $this->serverCount($this->orgA));
    }

    public function testMembersListIsScopedToOwnTenant(): void
    {
        $res = $this->call('GET', "/api/v1/tenants/{$this->orgA}/members", session: $this->adminA);
        self::assertSame(200, $res->status);
        self::assertCount(2, $this->json($res)['data']);
        self::assertStringNotContainsString('adminb', $res->body);

        self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$this->orgB}/members", session: $this->adminA)->status);
    }

    public function testMembersResponseNeverExposesSecrets(): void
    {
        $res = $this->call('GET', "/api/v1/tenants/{$this->orgA}/members", session: $this->adminA);
        foreach (['password', 'mfa_secret', 'argon2'] as $needle) {
            self::assertStringNotContainsStringIgnoringCase($needle, $res->body);
        }
        $me = $this->call('GET', '/api/v1/me', session: $this->adminA);
        foreach (['password', 'mfa_secret', 'argon2'] as $needle) {
            self::assertStringNotContainsStringIgnoringCase($needle, $me->body);
        }
    }

    public function testViewerCannotWriteInOwnTenant(): void
    {
        self::assertSame(200, $this->call('GET', "/api/v1/tenants/{$this->orgA}/servers", session: $this->viewerA)->status);

        $create = $this->call(
            'POST',
            "/api/v1/tenants/{$this->orgA}/servers",
            ['name' => 'x', 'hostname' => 'x.example.test'],
            $this->viewerA,
        );
        self::assertSame(403, $create->status);
        self::assertSame('forbidden', $this->json($create)['error']['code']);

        $patch = $this->call('PATCH', "/api/v1/tenants/{$this->orgA}/servers/{$this->serverA}", ['name' => 'x'], $this->viewerA);
        self::assertSame(403, $patch->status);

        self::assertSame(403, $this->call('GET', "/api/v1/tenants/{$this->orgA}/members", session: $this->viewerA)->status);
        self::assertSame(1, $this->serverCount($this->orgA));
    }

    public function testRoleIsPerTenantNotGlobal(): void
    {
        $multi = $this->fx->user('multi');
        $this->fx->member($this->orgA, $multi['id'], 'operator');
        $this->fx->member($this->orgB, $multi['id'], 'viewer');
        $s = $this->login($multi);

        $okA = $this->call('POST', "/api/v1/tenants/{$this->orgA}/servers", ['name' => 'n', 'hostname' => 'n.example.test'], $s);
        $noB = $this->call('POST', "/api/v1/tenants/{$this->orgB}/servers", ['name' => 'n', 'hostname' => 'n2.example.test'], $s);

        self::assertSame(201, $okA->status);
        self::assertSame(403, $noB->status);
        self::assertSame(200, $this->call('GET', "/api/v1/tenants/{$this->orgB}/servers", session: $s)->status);
    }

    public function testDisabledMembershipLosesAccessImmediately(): void
    {
        $u = $this->fx->user('leaver');
        $this->fx->member($this->orgA, $u['id'], 'operator');
        $s = $this->login($u);
        self::assertSame(200, $this->call('GET', "/api/v1/tenants/{$this->orgA}/servers", session: $s)->status);

        $this->pdo->exec("UPDATE organization_members SET status = 'disabled' WHERE user_id = {$u['id']}");

        self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$this->orgA}/servers", session: $s)->status);
    }

    public function testSuspendedOrganizationDeniesAllMembers(): void
    {
        $this->pdo->exec("UPDATE organizations SET status = 'suspended' WHERE id = {$this->orgA}");

        self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$this->orgA}/servers", session: $this->adminA)->status);
    }

    public function testUserWithNoMembershipSeesNothing(): void
    {
        $loner = $this->fx->user('loner');
        $s = $this->login($loner);

        foreach ([$this->orgA, $this->orgB] as $org) {
            self::assertSame(404, $this->call('GET', "/api/v1/tenants/{$org}/servers", session: $s)->status);
        }
        $me = $this->json($this->call('GET', '/api/v1/me', session: $s))['data'];
        self::assertSame([], $me['memberships']);
    }

    public function testMalformedTenantIdsAreRejectedAsNotFound(): void
    {
        foreach (['abc', '0', '-1', '1.5', '1e3', '01', '9999999999999999999999'] as $bad) {
            $res = $this->call('GET', "/api/v1/tenants/{$bad}/servers", session: $this->adminA);
            self::assertSame(404, $res->status, $bad);
        }
    }

    public function testCrossTenantAttemptsAreAuditedWithoutPollutingVictimTenant(): void
    {
        $this->call('GET', "/api/v1/tenants/{$this->orgB}/servers", session: $this->adminA);

        self::assertSame(1, $this->auditCount('authz.denied'));
        self::assertSame(0, $this->auditCount('authz.denied', $this->orgB), 'attacker must not write into victim audit log');
    }

    public function testAuthenticationIsRequiredForEveryTenantRoute(): void
    {
        $routes = [
            ['GET', "/api/v1/tenants/{$this->orgA}/servers"],
            ['POST', "/api/v1/tenants/{$this->orgA}/servers"],
            ['GET', "/api/v1/tenants/{$this->orgA}/servers/{$this->serverA}"],
            ['PATCH', "/api/v1/tenants/{$this->orgA}/servers/{$this->serverA}"],
            ['GET', "/api/v1/tenants/{$this->orgA}/members"],
            ['GET', '/api/v1/me'],
        ];
        foreach ($routes as [$method, $path]) {
            $res = $this->call($method, $path, $method === 'GET' ? null : ['name' => 'x', 'hostname' => 'x.example.test']);
            self::assertSame(401, $res->status, "{$method} {$path}");
        }
    }
}
