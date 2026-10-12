<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ServerWatch\Core\Kernel;
use ServerWatch\Core\Request;
use ServerWatch\Core\Router;
use ServerWatch\Http\HealthController;

final class KernelTest extends TestCase
{
    private function kernel(): Kernel
    {
        $router = new Router();
        $router->add('GET', '/health', [new HealthController(), 'show']);
        return new Kernel($router, static fn (): string => 'req-test');
    }

    public function testHealthReturnsOk(): void
    {
        $res = $this->kernel()->handle(new Request('GET', '/health'));

        self::assertSame(200, $res->status);
        self::assertSame('application/json', $res->headers['Content-Type']);
        self::assertSame(['status' => 'ok', 'service' => 'serverwatch-dashboard'], json_decode($res->body, true));
    }

    public function testHealthRejectsNonGet(): void
    {
        $res = $this->kernel()->handle(new Request('POST', '/health'));

        self::assertSame(405, $res->status);
        self::assertSame('GET', $res->headers['Allow']);
        self::assertSame('method_not_allowed', json_decode($res->body, true)['error']['code']);
    }

    public function testUnknownRouteUsesStructuredError(): void
    {
        $res = $this->kernel()->handle(new Request('GET', '/nope'));

        self::assertSame(404, $res->status);
        self::assertSame(
            ['error' => ['code' => 'not_found', 'message' => 'Resource not found.', 'request_id' => 'req-test']],
            json_decode($res->body, true),
        );
    }

    public function testUnexpectedExceptionBecomes500WithoutLeakingDetails(): void
    {
        $router = new Router();
        $router->add('GET', '/boom', static function (): never {
            throw new \RuntimeException('SECRET-INTERNAL-DETAIL');
        });
        $kernel = new Kernel($router, static fn (): string => 'req-test', static function (): void {
        });

        $res = $kernel->handle(new Request('GET', '/boom'));

        self::assertSame(500, $res->status);
        self::assertStringNotContainsString('SECRET-INTERNAL-DETAIL', $res->body);
        self::assertSame('internal_error', json_decode($res->body, true)['error']['code']);
    }
}
