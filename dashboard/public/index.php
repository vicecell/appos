<?php

declare(strict_types=1);

use ServerWatch\App;
use ServerWatch\Core\Config;
use ServerWatch\Core\Db;
use ServerWatch\Core\Request;
use ServerWatch\Core\Response;

require __DIR__ . '/../vendor/autoload.php';

$getenv = static fn (string $key): string|false => getenv($key);

try {
    $kernel = App::createKernel(Config::fromEnv($getenv), Db::fromEnv($getenv));
    $response = $kernel->handle(Request::fromGlobals());
} catch (Throwable $e) {
    // Misconfiguration (e.g. missing APP_KEY) must fail closed and say nothing about why.
    error_log('bootstrap failure: ' . $e::class);
    $response = Response::error(500, 'internal_error', 'Internal server error.', bin2hex(random_bytes(8)));
}

http_response_code($response->status);
foreach ($response->headers as $name => $value) {
    header($name . ': ' . $value);
}
echo $response->body;
