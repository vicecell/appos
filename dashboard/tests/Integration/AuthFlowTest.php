<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Integration;

use ServerWatch\Tests\Support\IntegrationTestCase;

final class AuthFlowTest extends IntegrationTestCase
{
    public function testLoginSetsHardenedCookieAndMeReturnsIdentity(): void
    {
        $org = $this->fx->org('Acme');
        $u = $this->fx->user('viewer');
        $this->fx->member($org, $u['id'], 'viewer');

        $res = $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => $u['password']]);

        self::assertSame(200, $res->status);
        $cookie = $res->headers['Set-Cookie'];
        self::assertStringContainsString('HttpOnly', $cookie);
        self::assertStringContainsString('SameSite=Lax', $cookie);
        self::assertStringContainsString('Path=/', $cookie);
        self::assertStringNotContainsString('Domain=', $cookie);
        self::assertSame('no-store', $res->headers['Cache-Control']);

        $s = $this->login($u);
        $me = $this->json($this->call('GET', '/api/v1/me', session: $s))['data'];
        self::assertSame($u['email'], $me['user']['email']);
        self::assertSame($org, $me['memberships'][0]['organization']['id']);
        self::assertSame('viewer', $me['memberships'][0]['role']);
        self::assertSame($s->csrf, $me['csrf_token']);
    }

    public function testSecureConfigAddsSecureFlagAndHostPrefix(): void
    {
        $this->config = new \ServerWatch\Core\Config(str_repeat("\x07", 32), cookieSecure: true);
        $this->kernel = \ServerWatch\App::createKernel(
            $this->config,
            new \ServerWatch\Core\Db(fn (): \PDO => $this->pdo),
            $this->clock,
            $this->hasher,
            static fn (): string => 'req-test',
        );
        $u = $this->fx->user('viewer');

        $res = $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => $u['password']]);

        self::assertStringStartsWith('__Host-sw_session=', $res->headers['Set-Cookie']);
        self::assertStringContainsString('Secure', $res->headers['Set-Cookie']);
    }

    public function testSessionTokenIsStoredOnlyAsHash(): void
    {
        $u = $this->fx->user('viewer');
        $s = $this->login($u);

        $ids = $this->queryColumn('SELECT id FROM sessions');
        self::assertCount(1, $ids);
        self::assertNotSame($s->token, $ids[0]);
        self::assertSame(hash('sha256', $s->token), $ids[0]);
    }

    public function testWrongPasswordAndUnknownUserAreIndistinguishable(): void
    {
        $u = $this->fx->user('viewer');

        $wrong = $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => 'nope-nope-nope']);
        $unknown = $this->call('POST', '/api/v1/auth/login', ['email' => 'ghost@example.test', 'password' => 'nope-nope-nope']);

        self::assertSame(401, $wrong->status);
        self::assertSame($wrong->status, $unknown->status);
        self::assertSame($wrong->body, $unknown->body);
        self::assertArrayNotHasKey('Set-Cookie', $wrong->headers);
    }

    public function testDisabledUserCannotLoginAndLooksLikeBadCredentials(): void
    {
        $u = $this->fx->user('banned', status: 'disabled');
        $res = $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => $u['password']]);
        $bad = $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => 'wrong-wrong-wrong']);

        self::assertSame(401, $res->status);
        self::assertSame($bad->body, $res->body);
    }

    public function testEmailIsCaseInsensitive(): void
    {
        $u = $this->fx->user('viewer');
        $res = $this->call('POST', '/api/v1/auth/login', ['email' => '  ' . strtoupper($u['email']) . ' ', 'password' => $u['password']]);
        self::assertSame(200, $res->status);
    }

    public function testBruteForceIsThrottledPerAccountAndRecoversAfterWindow(): void
    {
        $u = $this->fx->user('viewer');
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(401, $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => "bad-$i-bad-bad"], ip: "198.51.100.$i")->status);
        }

        // Even the CORRECT password from a fresh IP is refused while throttled.
        $blocked = $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => $u['password']], ip: '198.51.100.99');
        self::assertSame(429, $blocked->status);
        self::assertGreaterThan(0, (int) $blocked->headers['Retry-After']);
        self::assertSame('rate_limited', $this->json($blocked)['error']['code']);

        $this->clock->advance(901);
        self::assertSame(200, $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => $u['password']], ip: '198.51.100.99')->status);
    }

    public function testBruteForceIsThrottledPerIpAcrossAccounts(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->call('POST', '/api/v1/auth/login', ['email' => "victim$i@example.test", 'password' => 'bad-bad-bad-bad'], ip: '192.0.2.50');
        }
        $u = $this->fx->user('viewer');

        $res = $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => $u['password']], ip: '192.0.2.50');
        self::assertSame(429, $res->status);
    }

    public function testSuccessfulLoginClearsAccountFailureCounter(): void
    {
        $u = $this->fx->user('viewer');
        for ($i = 0; $i < 4; $i++) {
            $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => 'bad-bad-bad-bad']);
        }
        self::assertSame(200, $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => $u['password']])->status);
        for ($i = 0; $i < 4; $i++) {
            self::assertSame(401, $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => 'bad-bad-bad-bad'])->status);
        }
    }

    public function testPrivilegedAccountWithoutMfaIsDeniedEvenWithCorrectPassword(): void
    {
        $org = $this->fx->org('Acme');
        $admin = $this->fx->user('admin');
        $this->fx->member($org, $admin['id'], 'client_admin');
        $platform = $this->fx->user('root', platformAdmin: true);

        foreach ([$admin, $platform] as $u) {
            $res = $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => $u['password']]);
            self::assertSame(403, $res->status);
            self::assertSame('mfa_setup_required', $this->json($res)['error']['code']);
            self::assertArrayNotHasKey('Set-Cookie', $res->headers);
        }
    }

    public function testDisabledAdminMembershipDoesNotMakeUserPrivileged(): void
    {
        $org = $this->fx->org('Acme');
        $u = $this->fx->user('formeradmin');
        $this->fx->member($org, $u['id'], 'client_admin', 'disabled');

        self::assertSame(200, $this->call('POST', '/api/v1/auth/login', ['email' => $u['email'], 'password' => $u['password']])->status);
    }

    public function testMfaFlow(): void
    {
        $org = $this->fx->org('Acme');
        $admin = $this->fx->user('admin', mfa: true);
        $this->fx->member($org, $admin['id'], 'client_admin');
        $creds = ['email' => $admin['email'], 'password' => $admin['password']];
        self::assertNotNull($admin['totp_secret']);

        $missing = $this->call('POST', '/api/v1/auth/login', $creds);
        self::assertSame(401, $missing->status);
        self::assertSame('mfa_required', $this->json($missing)['error']['code']);

        $wrong = $this->call('POST', '/api/v1/auth/login', $creds + ['totp_code' => '000000']);
        self::assertSame(401, $wrong->status);
        self::assertSame('invalid_credentials', $this->json($wrong)['error']['code']);

        $code = $this->fx->totpNow($admin['totp_secret']);
        self::assertSame(200, $this->call('POST', '/api/v1/auth/login', $creds + ['totp_code' => $code])->status);

        // same code again is a replay
        $replay = $this->call('POST', '/api/v1/auth/login', $creds + ['totp_code' => $code]);
        self::assertSame(401, $replay->status);

        // an older step than the last accepted one is also refused
        $old = $this->fx->totpNow($admin['totp_secret'], -1);
        self::assertSame(401, $this->call('POST', '/api/v1/auth/login', $creds + ['totp_code' => $old])->status);

        // the next step is accepted
        $this->clock->advance(30);
        self::assertSame(200, $this->call('POST', '/api/v1/auth/login', $creds + ['totp_code' => $this->fx->totpNow($admin['totp_secret'])])->status);
    }

    public function testMfaSecretIsEncryptedAtRest(): void
    {
        $admin = $this->fx->user('admin', mfa: true);
        $raw = $this->queryValue("SELECT mfa_secret_enc FROM users WHERE id = {$admin['id']}");

        self::assertIsString($raw);
        self::assertStringNotContainsString(\ServerWatch\Auth\Base32::decode((string) $admin['totp_secret']), $raw);
    }

    public function testWrongTotpCountsTowardsThrottle(): void
    {
        $admin = $this->fx->user('admin', mfa: true);
        $creds = ['email' => $admin['email'], 'password' => $admin['password']];
        for ($i = 0; $i < 5; $i++) {
            $this->call('POST', '/api/v1/auth/login', $creds + ['totp_code' => sprintf('%06d', $i)]);
        }
        self::assertSame(429, $this->call('POST', '/api/v1/auth/login', $creds + ['totp_code' => '123456'])->status);
    }

    public function testSessionIdleAndAbsoluteExpiry(): void
    {
        $u = $this->fx->user('viewer');
        $s = $this->login($u);
        self::assertSame(200, $this->call('GET', '/api/v1/me', session: $s)->status);

        $this->clock->advance(29 * 60);
        self::assertSame(200, $this->call('GET', '/api/v1/me', session: $s)->status, 'activity extends idle window');

        $this->clock->advance(31 * 60);
        self::assertSame(401, $this->call('GET', '/api/v1/me', session: $s)->status, 'idle timeout');

        $s2 = $this->login($u);
        for ($i = 0; $i < 25; $i++) {
            $this->clock->advance(29 * 60);
            $status = $this->call('GET', '/api/v1/me', session: $s2)->status;
            if ($status === 401) {
                break;
            }
        }
        self::assertSame(401, $status, 'absolute lifetime (12h) must end the session despite activity');
    }

    public function testEachLoginIssuesNewSessionToken(): void
    {
        $u = $this->fx->user('viewer');
        self::assertNotSame($this->login($u)->token, $this->login($u)->token);
    }

    public function testLogoutRevokesSessionServerSide(): void
    {
        $u = $this->fx->user('viewer');
        $s = $this->login($u);

        $res = $this->call('POST', '/api/v1/auth/logout', [], $s);
        self::assertSame(200, $res->status);
        self::assertStringContainsString('Max-Age=0', $res->headers['Set-Cookie']);

        self::assertSame(401, $this->call('GET', '/api/v1/me', session: $s)->status, 'old token must be dead');
    }

    public function testDisablingUserKillsExistingSessions(): void
    {
        $u = $this->fx->user('viewer');
        $s = $this->login($u);
        $this->pdo->exec("UPDATE users SET status = 'disabled' WHERE id = {$u['id']}");

        self::assertSame(401, $this->call('GET', '/api/v1/me', session: $s)->status);
    }

    public function testStateChangingRequestsRequireValidCsrfToken(): void
    {
        $org = $this->fx->org('Acme');
        $u = $this->fx->user('op');
        $this->fx->member($org, $u['id'], 'operator');
        $s = $this->login($u);
        $path = "/api/v1/tenants/{$org}/servers";
        $body = ['name' => 'n', 'hostname' => 'n.example.test'];

        $none = $this->call('POST', $path, $body, $s, csrf: false);
        $wrong = $this->call('POST', $path, $body, $s, headers: ['x-csrf-token' => 'forged'], csrf: false);
        $otherSession = $this->call('POST', $path, $body, $s, headers: ['x-csrf-token' => $this->login($u)->csrf], csrf: false);

        foreach ([$none, $wrong, $otherSession] as $res) {
            self::assertSame(403, $res->status);
            self::assertSame('csrf_invalid', $this->json($res)['error']['code']);
        }
        self::assertSame(0, $this->serverCount($org));
        self::assertSame(201, $this->call('POST', $path, $body, $s)->status);
        self::assertSame(200, $this->call('GET', $path, session: $s, csrf: false)->status, 'safe methods need no CSRF');
    }

    public function testSessionCookieFromAnotherUserCannotBeReplayedWithGuessedToken(): void
    {
        $res = $this->call('GET', '/api/v1/me', session: new \ServerWatch\Tests\Support\Session(str_repeat('a', 43), 'x'));
        self::assertSame(401, $res->status);
    }

    public function testLoginRejectsBadInputAndWrongContentType(): void
    {
        self::assertSame(422, $this->call('POST', '/api/v1/auth/login', ['email' => 'x'])->status);
        self::assertSame(422, $this->call('POST', '/api/v1/auth/login', ['email' => ['a'], 'password' => 'p'])->status);
        self::assertSame(422, $this->call('POST', '/api/v1/auth/login', ['email' => 'a@b.test', 'password' => str_repeat('x', 5000)])->status);

        $res = $this->kernel->handle(new \ServerWatch\Core\Request(
            'POST',
            '/api/v1/auth/login',
            [],
            ['content-type' => 'application/x-www-form-urlencoded'],
            [],
            'email=a@b.test&password=x',
        ));
        self::assertSame(415, $res->status);

        $bad = $this->kernel->handle(new \ServerWatch\Core\Request(
            'POST',
            '/api/v1/auth/login',
            [],
            ['content-type' => 'application/json'],
            [],
            '{not json',
        ));
        self::assertSame(400, $bad->status);

        $big = $this->kernel->handle(new \ServerWatch\Core\Request(
            'POST',
            '/api/v1/auth/login',
            [],
            ['content-type' => 'application/json'],
            [],
            json_encode(['email' => 'a@b.test', 'password' => str_repeat('x', 70000)], JSON_THROW_ON_ERROR),
        ));
        self::assertSame(413, $big->status);
    }

    public function testPasswordIsRehashedOnLoginWhenParametersAreWeaker(): void
    {
        $u = $this->fx->user('viewer');
        $strong = new \ServerWatch\Auth\PasswordHasher(['memory_cost' => 2048, 'time_cost' => 2, 'threads' => 1]);
        $this->kernel = \ServerWatch\App::createKernel(
            $this->config,
            new \ServerWatch\Core\Db(fn (): \PDO => $this->pdo),
            $this->clock,
            $strong,
            static fn (): string => 'req-test',
        );
        $before = $this->queryValue("SELECT password_hash FROM users WHERE id = {$u['id']}");

        $this->login($u);

        $after = $this->queryValue("SELECT password_hash FROM users WHERE id = {$u['id']}");
        self::assertNotSame($before, $after);
        self::assertStringContainsString('m=2048,t=2', (string) $after);
    }
}
