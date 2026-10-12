<?php

declare(strict_types=1);

namespace ServerWatch;

use ServerWatch\Auth\AuthService;
use ServerWatch\Auth\Csrf;
use ServerWatch\Auth\LoginThrottle;
use ServerWatch\Auth\PasswordHasher;
use ServerWatch\Auth\SecretBox;
use ServerWatch\Auth\SessionStore;
use ServerWatch\Authz\Authorizer;
use ServerWatch\Authz\Permission;
use ServerWatch\Core\Clock;
use ServerWatch\Core\Config;
use ServerWatch\Core\Db;
use ServerWatch\Core\Kernel;
use ServerWatch\Core\Router;
use ServerWatch\Core\SystemClock;
use ServerWatch\Http\AuthController;
use ServerWatch\Http\Guard;
use ServerWatch\Http\HealthController;
use ServerWatch\Http\MemberController;
use ServerWatch\Http\ServerController;
use ServerWatch\Repositories\AuditLog;
use ServerWatch\Repositories\GrantRepository;
use ServerWatch\Repositories\MemberRepository;
use ServerWatch\Repositories\ServerRepository;
use ServerWatch\Repositories\UserRepository;

/**
 * Composition root: wires services and the route table. Routes are public unless marked auth: true, and every
 * tenant route declares the permission it needs, so "forgot the check" is a routing-table review, not a hunt.
 */
final class App
{
    /**
     * @param (\Closure(): string)|null $requestIdFactory
     * @param (\Closure(string): void)|null $errorLogger
     */
    public static function createKernel(
        Config $config,
        Db $db,
        ?Clock $clock = null,
        ?PasswordHasher $hasher = null,
        ?\Closure $requestIdFactory = null,
        ?\Closure $errorLogger = null,
    ): Kernel {
        $clock ??= new SystemClock();
        $hasher ??= new PasswordHasher();

        $audit = new AuditLog($db, $clock);
        $users = new UserRepository($db);
        $members = new MemberRepository($db);
        $grants = new GrantRepository($db);
        $servers = new ServerRepository($db, $clock);

        $sessions = new SessionStore($db, $clock, $config);
        $csrf = new Csrf($config->appKey);
        $throttle = new LoginThrottle($db, $clock, $config);
        $authService = new AuthService($users, $hasher, new SecretBox($config->appKey), $sessions, $throttle, $audit, $clock);
        $authorizer = new Authorizer($members, $grants, $clock);
        $guard = new Guard($config, $sessions, $csrf, $authorizer, $audit);

        $auth = new AuthController($config, $authService, $sessions, $csrf, $users, $audit, $guard);
        $serverCtl = new ServerController($servers, $audit);
        $memberCtl = new MemberController($members);

        $r = new Router();
        $r->add('GET', '/health', [new HealthController(), 'show']);

        $r->add('POST', '/api/v1/auth/login', [$auth, 'login']);
        $r->add('POST', '/api/v1/auth/logout', [$auth, 'logout'], auth: true);
        $r->add('GET', '/api/v1/me', [$auth, 'me'], auth: true);

        $base = '/api/v1/tenants/{tenant}';
        $r->add('GET', $base . '/servers', [$serverCtl, 'index'], true, Permission::ServersRead);
        $r->add('POST', $base . '/servers', [$serverCtl, 'create'], true, Permission::ServersWrite);
        $r->add('GET', $base . '/servers/{id}', [$serverCtl, 'show'], true, Permission::ServersRead);
        $r->add('PATCH', $base . '/servers/{id}', [$serverCtl, 'update'], true, Permission::ServersWrite);
        $r->add('GET', $base . '/members', [$memberCtl, 'index'], true, Permission::MembersRead);

        return new Kernel($r, $requestIdFactory, $errorLogger, $guard);
    }
}
