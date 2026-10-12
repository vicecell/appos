<?php

declare(strict_types=1);

use ServerWatch\Core\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
$path = parse_url($uri, PHP_URL_PATH);

$response = (new Kernel())->handle($method, is_string($path) ? $path : '/');

http_response_code($response->status);
foreach ($response->headers as $name => $value) {
    header($name . ': ' . $value);
}
echo $response->body;
