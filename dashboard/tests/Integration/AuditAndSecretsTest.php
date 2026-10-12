<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Integration;

use ServerWatch\Repositories\AuditLog;
use ServerWatch\Core\Db;
use ServerWatch\Tests\Support\IntegrationTestCase;

final class AuditAndSecretsTest extends IntegrationTestCase
{
    public function testLoginSuccessAndFailureAreAuditedWithoutSecrets(): void
    {
        $u = $this->fx->user('viewer');
        $sentinel = 'S3NTINEL-wrong-password';

        $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => $sentinel]);
        $s = $this->login($u);
        $this->call('POST', '/api/v1/auth/logout', [], $s);

        self::assertSame(1, $this->auditCount('login.failure'));
        self::assertSame(1, $this->auditCount('login.success'));
        self::assertSame(1, $this->auditCount('logout'));

        $dump = json_encode($this->queryAll('SELECT * FROM audit_logs'), JSON_THROW_ON_ERROR);
        foreach ([$sentinel, $u['password'], $s->token, $s->csrf, 'argon2'] as $secret) {
            self::assertStringNotContainsString($secret, $dump);
        }
        // the attempted e-mail address is not stored for failures against unknown accounts either
        $this->call('POST', '/api/v1/auth/login', ['email' => 'ghost@example.test', 'password' => 'x-x-x-x-x-x-x-x']);
        $dump = json_encode($this->queryAll('SELECT * FROM audit_logs'), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('ghost@example.test', $dump);
    }

    public function testErrorResponsesNeverLeakInternalsOrSecrets(): void
    {
        $u = $this->fx->user('viewer');
        $s = $this->login($u);
        foreach (['/api/v1/me', '/api/v1/tenants/1/servers', '/api/v1/unknown'] as $path) {
            $res = $this->call('GET', $path, session: $s);
            foreach (['PDOException', 'SQLSTATE', 'Stack trace', '.php', $u['password'], $s->token] as $needle) {
                self::assertStringNotContainsString($needle, $res->body);
            }
        }
    }

    public function testDatabaseFailureFailsClosedWithGeneric500(): void
    {
        $u = $this->fx->user('viewer');
        $s = $this->login($u);
        $org = $this->fx->org('Acme');
        $this->fx->member($org, $u['id'], 'viewer');

        // Same app, but the database is unreachable: a valid cookie must never turn into access or a 401/404 guess.
        $broken = \ServerWatch\App::createKernel(
            $this->config,
            new Db(static function (): \PDO {
                throw new \PDOException('SQLSTATE[HY000] [2002] connection refused to db.internal.example');
            }),
            $this->clock,
            $this->hasher,
            static fn (): string => 'req-test',
            static function (): void {
            },
        );
        $res = $broken->handle(new \ServerWatch\Core\Request(
            'GET',
            "/api/v1/tenants/{$org}/servers",
            [],
            [],
            [$this->config->cookieName() => $s->token],
        ));

        self::assertSame(500, $res->status);
        self::assertSame('internal_error', $this->json($res)['error']['code']);
        self::assertStringNotContainsString('db.internal', $res->body);
        self::assertStringNotContainsString('SQLSTATE', $res->body);
    }

    public function testAuditLogRefusesSensitiveMetadataKeys(): void
    {
        $audit = new AuditLog(new Db(fn (): \PDO => $this->pdo), $this->clock);

        foreach (['password', 'token', 'csrf', 'secret', 'cookie', 'Authorization', 'mfa_code', 'totp_code'] as $key) {
            try {
                $audit->record(null, null, 'test.action', null, null, '127.0.0.1', [$key => 'x']);
                self::fail("accepted sensitive metadata key {$key}");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $audit->record(null, null, 'test.action', null, null, '127.0.0.1', ['permission' => 'servers.write']);
        self::assertSame(1, $this->auditCount('test.action'));
    }
}
