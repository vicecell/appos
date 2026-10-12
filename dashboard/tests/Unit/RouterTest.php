<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ServerWatch\Authz\Permission;
use ServerWatch\Core\Router;

final class RouterTest extends TestCase
{
    private function router(): Router
    {
        $r = new Router();
        $r->add('GET', '/api/v1/tenants/{tenant}/servers', 'list', auth: true, permission: Permission::ServersRead);
        $r->add('POST', '/api/v1/tenants/{tenant}/servers', 'create', auth: true, permission: Permission::ServersWrite);
        $r->add('GET', '/api/v1/tenants/{tenant}/servers/{id}', 'show', auth: true, permission: Permission::ServersRead);
        $r->add('POST', '/api/v1/auth/login', 'login');
        return $r;
    }

    public function testMatchesAndExtractsParams(): void
    {
        $m = $this->router()->match('GET', '/api/v1/tenants/42/servers/7');

        self::assertNotNull($m->route);
        self::assertSame('show', $m->route->handler);
        self::assertSame(['tenant' => '42', 'id' => '7'], $m->params);
        self::assertSame(Permission::ServersRead, $m->route->permission);
        self::assertTrue($m->route->auth);
    }

    public function testUnknownPathIsNotFound(): void
    {
        $m = $this->router()->match('GET', '/api/v1/nope');
        self::assertNull($m->route);
        self::assertSame([], $m->allowedMethods);
    }

    public function testWrongMethodReportsAllowedMethods(): void
    {
        $m = $this->router()->match('DELETE', '/api/v1/tenants/1/servers');
        self::assertNull($m->route);
        self::assertSame(['GET', 'POST'], $m->allowedMethods);
    }

    public function testParamsNeverSpanSegmentsOrMatchTraversal(): void
    {
        $r = $this->router();
        self::assertNull($r->match('GET', '/api/v1/tenants/1/2/servers')->route);
        self::assertNull($r->match('GET', '/api/v1/tenants//servers')->route);
        self::assertNull($r->match('GET', '/api/v1/tenants/1/servers/')->route, 'trailing slash is a different path');
    }
}
