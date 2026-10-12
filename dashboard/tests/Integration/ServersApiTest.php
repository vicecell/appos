<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Integration;

use ServerWatch\Tests\Support\IntegrationTestCase;
use ServerWatch\Tests\Support\Session;

final class ServersApiTest extends IntegrationTestCase
{
    private int $org;
    private int $actor;
    private Session $op;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = $this->fx->org('Acme');
        $u = $this->fx->user('op');
        $this->actor = $u['id'];
        $this->fx->member($this->org, $u['id'], 'operator');
        $this->op = $this->login($u);
    }

    private function path(string $suffix = ''): string
    {
        return "/api/v1/tenants/{$this->org}/servers{$suffix}";
    }

    public function testCreateReturnsResourceAndWritesAuditTrail(): void
    {
        $res = $this->call('POST', $this->path(), ['name' => '  Web 1 ', 'hostname' => 'WEB1.Example.Test'], $this->op);

        self::assertSame(201, $res->status);
        $data = $this->json($res)['data'];
        self::assertSame('Web 1', $data['name']);
        self::assertSame('web1.example.test', $data['hostname']);
        self::assertSame('pending', $data['status']);
        self::assertSame('2026-10-12T06:00:00.000Z', $data['created_at']);
        self::assertArrayNotHasKey('organization_id', $data);

        $row = $this->queryAll("SELECT * FROM audit_logs WHERE action = 'server.create'")[0];
        self::assertSame($this->org, (int) $row['organization_id']);
        self::assertSame($this->actor, (int) $row['actor_id']);
        self::assertSame('server', $row['target_type']);
        self::assertSame((string) $data['id'], $row['target_id']);
        self::assertSame('203.0.113.9', $row['ip_address']);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidCreates(): array
    {
        return [
            'missing name' => [['hostname' => 'a.example.test']],
            'missing hostname' => [['name' => 'x']],
            'empty name' => [['name' => '   ', 'hostname' => 'a.example.test']],
            'long name' => [['name' => str_repeat('n', 101), 'hostname' => 'a.example.test']],
            'control char name' => [['name' => "bad\x00name", 'hostname' => 'a.example.test']],
            'non-string name' => [['name' => 5, 'hostname' => 'a.example.test']],
            'url as hostname' => [['name' => 'x', 'hostname' => 'http://a.example.test']],
            'space in hostname' => [['name' => 'x', 'hostname' => 'a b.example.test']],
            'leading hyphen' => [['name' => 'x', 'hostname' => '-a.example.test']],
            'double dot' => [['name' => 'x', 'hostname' => 'a..example.test']],
            'long label' => [['name' => 'x', 'hostname' => str_repeat('a', 64) . '.test']],
            'long hostname' => [['name' => 'x', 'hostname' => str_repeat('a.', 130) . 'test']],
            'unknown field' => [['name' => 'x', 'hostname' => 'a.example.test', 'status' => 'online']],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCreates')]
    public function testCreateValidation(array $body): void
    {
        $res = $this->call('POST', $this->path(), $body, $this->op);

        self::assertSame(422, $res->status);
        self::assertSame('validation_failed', $this->json($res)['error']['code']);
        self::assertSame(0, $this->serverCount($this->org));
    }

    public function testHostnameIsUniquePerTenantOnly(): void
    {
        $other = $this->fx->org('Other');
        $body = ['name' => 'x', 'hostname' => 'shared.example.test'];

        self::assertSame(201, $this->call('POST', $this->path(), $body, $this->op)->status);
        $dup = $this->call('POST', $this->path(), $body, $this->op);
        self::assertSame(409, $dup->status);
        self::assertSame('conflict', $this->json($dup)['error']['code']);

        $this->fx->server($other, 'theirs', 'shared2.example.test');
        $u = $this->fx->user('otherop');
        $this->fx->member($other, $u['id'], 'operator');
        $s = $this->login($u);
        self::assertSame(201, $this->call('POST', "/api/v1/tenants/{$other}/servers", $body, $s)->status);
    }

    public function testListPaginatesWithCursor(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->fx->server($this->org, "s{$i}");
        }

        $p1 = $this->json($this->call('GET', $this->path(), session: $this->op, query: ['limit' => '2']));
        self::assertSame(['s1', 's2'], array_column($p1['data'], 'name'));
        self::assertNotNull($p1['next_cursor']);

        $p2 = $this->json($this->call('GET', $this->path(), session: $this->op, query: ['limit' => '2', 'cursor' => $p1['next_cursor']]));
        self::assertSame(['s3', 's4'], array_column($p2['data'], 'name'));

        $p3 = $this->json($this->call('GET', $this->path(), session: $this->op, query: ['limit' => '2', 'cursor' => $p2['next_cursor']]));
        self::assertSame(['s5'], array_column($p3['data'], 'name'));
        self::assertNull($p3['next_cursor']);
    }

    public function testListRejectsBadPagingParameters(): void
    {
        foreach ([['limit' => '0'], ['limit' => '101'], ['limit' => 'abc'], ['cursor' => 'zzz'], ['cursor' => '-5']] as $q) {
            self::assertSame(422, $this->call('GET', $this->path(), session: $this->op, query: $q)->status, json_encode($q, JSON_THROW_ON_ERROR));
        }
    }

    public function testShowAndRename(): void
    {
        $id = $this->fx->server($this->org, 'old', 'old.example.test');

        $show = $this->call('GET', $this->path("/{$id}"), session: $this->op);
        self::assertSame(200, $show->status);
        self::assertSame('old', $this->json($show)['data']['name']);

        $patch = $this->call('PATCH', $this->path("/{$id}"), ['name' => 'new'], $this->op);
        self::assertSame(200, $patch->status);
        self::assertSame('new', $this->json($patch)['data']['name']);
        self::assertSame(1, $this->auditCount('server.update', $this->org));
    }

    public function testPatchValidation(): void
    {
        $id = $this->fx->server($this->org, 'old');

        self::assertSame(422, $this->call('PATCH', $this->path("/{$id}"), [], $this->op)->status);
        self::assertSame(422, $this->call('PATCH', $this->path("/{$id}"), ['name' => ''], $this->op)->status);
        self::assertSame(422, $this->call('PATCH', $this->path("/{$id}"), ['hostname' => 'x.example.test'], $this->op)->status);
        self::assertSame(404, $this->call('PATCH', $this->path('/999999'), ['name' => 'x'], $this->op)->status);
    }

    public function testNonNumericServerIdIsNotFound(): void
    {
        foreach (['abc', '0', '-1', '1;DROP TABLE servers', '1%20OR%201=1'] as $bad) {
            self::assertSame(404, $this->call('GET', $this->path('/' . $bad), session: $this->op)->status, $bad);
        }
        self::assertSame(0, $this->serverCount($this->org));
    }

    public function testSqlInjectionAttemptsAreStoredLiterallyNotExecuted(): void
    {
        $evil = "x'); DROP TABLE servers; --";
        $res = $this->call('POST', $this->path(), ['name' => $evil, 'hostname' => 'inj.example.test'], $this->op);

        self::assertSame(201, $res->status);
        self::assertSame($evil, $this->json($res)['data']['name']);
        self::assertSame(1, $this->serverCount($this->org));
    }

    public function testResponsesEscapeHtmlAsDataAndCarryNosniff(): void
    {
        $res = $this->call('POST', $this->path(), ['name' => '<script>alert(1)</script>', 'hostname' => 'xss.example.test'], $this->op);

        self::assertSame('application/json', $res->headers['Content-Type']);
        self::assertSame('nosniff', $res->headers['X-Content-Type-Options']);
    }
}
