<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;
use ServerWatch\App;
use ServerWatch\Auth\PasswordHasher;
use ServerWatch\Auth\SecretBox;
use ServerWatch\Core\Config;
use ServerWatch\Core\Db;
use ServerWatch\Core\Kernel;
use ServerWatch\Core\MigrationCatalog;
use ServerWatch\Core\Migrator;
use ServerWatch\Core\Request;
use ServerWatch\Core\Response;

/**
 * Runs against a throwaway MySQL database (DB_TEST_NAME, must end in "_test") that has the real migrations
 * applied. Each test runs inside a transaction that is rolled back. Skipped when DB_* is unset.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected PDO $pdo;
    protected FixedClock $clock;
    protected Config $config;
    protected Fixtures $fx;
    protected Kernel $kernel;
    protected PasswordHasher $hasher;
    protected SecretBox $box;

    public static function connectTestDb(): PDO
    {
        $name = getenv('DB_TEST_NAME');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASSWORD');
        if ($name === false || $user === false || $pass === false) {
            self::markTestSkipped('DB_TEST_NAME / DB_USER / DB_PASSWORD not set');
        }
        if (!str_ends_with($name, '_test')) {
            throw new \RuntimeException('Refusing to run tests against a database that does not end in _test');
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_PORT') ?: '3306',
            $name,
        );
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }

    public static function dropAllTables(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $pdo->query('SHOW TABLES');
        foreach ($tables === false ? [] : $tables->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '', (string) $table) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function setUp(): void
    {
        $this->pdo = self::connectTestDb();
        $catalog = new MigrationCatalog(__DIR__ . '/../../database/migrations');
        $migrator = new Migrator($this->pdo, $catalog);
        if (count($migrator->applied()) !== count($catalog->all())) {
            self::dropAllTables($this->pdo);
            $migrator->up();
        }

        $this->clock = new FixedClock();
        $this->config = new Config(appKey: str_repeat("\x07", 32), cookieSecure: false);
        $this->hasher = new PasswordHasher(['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]);
        $this->box = new SecretBox($this->config->appKey);
        $this->fx = new Fixtures($this->pdo, $this->hasher, $this->box, $this->clock);
        $this->kernel = App::createKernel(
            $this->config,
            new Db(fn (): PDO => $this->pdo),
            $this->clock,
            $this->hasher,
            static fn (): string => 'req-test',
        );
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     * @param array<string, string> $query
     */
    protected function call(
        string $method,
        string $path,
        ?array $body = null,
        ?Session $session = null,
        array $headers = [],
        array $query = [],
        string $ip = '203.0.113.9',
        bool $csrf = true,
    ): Response {
        $cookies = [];
        if ($session !== null) {
            $cookies[$this->config->cookieName()] = $session->token;
            if ($csrf) {
                $headers['x-csrf-token'] = $session->csrf;
            }
        }
        $raw = null;
        if ($body !== null) {
            $raw = json_encode($body, JSON_THROW_ON_ERROR);
            $headers['content-type'] = 'application/json';
        }
        return $this->kernel->handle(new Request($method, $path, $query, $headers, $cookies, $raw, $ip));
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(Response $res): array
    {
        $data = json_decode($res->body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        return $data;
    }

    /**
     * @param array{email: string, password: string, totp_secret: string|null} $user
     */
    protected function login(array $user, ?string $totp = null, string $ip = '203.0.113.9'): Session
    {
        $body = ['email' => $user['email'], 'password' => $user['password']];
        if ($totp !== null) {
            $body['totp_code'] = $totp;
        }
        $res = $this->call('POST', '/api/v1/auth/login', $body, ip: $ip);
        self::assertSame(200, $res->status, 'login failed: ' . $res->body);
        $cookie = $res->headers['Set-Cookie'] ?? '';
        self::assertSame(
            1,
            preg_match('/^' . preg_quote($this->config->cookieName(), '/') . '=([^;]+);/', $cookie, $m),
            'missing session cookie',
        );
        $data = $this->json($res)['data'];
        self::assertIsArray($data);
        return new Session($m[1] ?? '', (string) $data['csrf_token']);
    }

    /**
     * @param array{email: string, password: string, totp_secret: string|null} $user
     */
    protected function loginWithMfa(array $user): Session
    {
        self::assertNotNull($user['totp_secret']);
        return $this->login($user, $this->fx->totpNow($user['totp_secret']));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function queryAll(string $sql): array
    {
        $stmt = $this->pdo->query($sql);
        self::assertNotFalse($stmt);
        return array_values($stmt->fetchAll());
    }

    /**
     * @return list<mixed>
     */
    protected function queryColumn(string $sql): array
    {
        $stmt = $this->pdo->query($sql);
        self::assertNotFalse($stmt);
        return array_values($stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    protected function queryValue(string $sql): mixed
    {
        $stmt = $this->pdo->query($sql);
        self::assertNotFalse($stmt);
        return $stmt->fetchColumn();
    }

    protected function auditCount(string $action, ?int $orgId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM audit_logs WHERE action = ?';
        $args = [$action];
        if ($orgId !== null) {
            $sql .= ' AND organization_id = ?';
            $args[] = $orgId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        return (int) $stmt->fetchColumn();
    }

    protected function serverCount(int $orgId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM servers WHERE organization_id = ?');
        $stmt->execute([$orgId]);
        return (int) $stmt->fetchColumn();
    }
}
