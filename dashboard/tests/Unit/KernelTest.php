<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ServerWatch\Core\Kernel;

final class KernelTest extends TestCase
{
    private function kernel(): Kernel
    {
        return new Kernel(static fn (): string => 'req-test');
    }

    public function testHealthReturnsOk(): void
    {
        $res = $this->kernel()->handle('GET', '/health');

        self::assertSame(200, $res->status);
        self::assertSame('application/json', $res->headers['Content-Type']);
        self::assertSame(['status' => 'ok', 'service' => 'serverwatch-dashboard'], json_decode($res->body, true));
    }

    public function testHealthRejectsNonGet(): void
    {
        $res = $this->kernel()->handle('POST', '/health');

        self::assertSame(405, $res->status);
        self::assertSame('GET', $res->headers['Allow']);
        self::assertSame('method_not_allowed', json_decode($res->body, true)['error']['code']);
    }

    public function testUnknownRouteUsesStructuredError(): void
    {
        $res = $this->kernel()->handle('GET', '/nope');

        self::assertSame(404, $res->status);
        self::assertSame(
            ['error' => ['code' => 'not_found', 'message' => 'Resource not found.', 'request_id' => 'req-test']],
            json_decode($res->body, true),
        );
    }
}
