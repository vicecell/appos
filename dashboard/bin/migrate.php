<?php

declare(strict_types=1);

use ServerWatch\Core\MigrationCatalog;
use ServerWatch\Core\Migrator;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Usage: php bin/migrate.php up|down|status
 * Credentials come from the environment (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD); never logged.
 */
$command = $argv[1] ?? 'status';

$env = static function (string $key, ?string $default = null): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        if ($default === null) {
            fwrite(STDERR, "Missing required environment variable {$key}\n");
            exit(2);
        }
        return $default;
    }
    return $value;
};

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $env('DB_HOST', '127.0.0.1'),
    $env('DB_PORT', '3306'),
    $env('DB_NAME'),
);
$pdo = new PDO($dsn, $env('DB_USER'), $env('DB_PASSWORD'));
$pdo->exec("SET time_zone = '+00:00'");

$migrator = new Migrator($pdo, new MigrationCatalog(__DIR__ . '/../database/migrations'));

switch ($command) {
    case 'up':
        $ran = $migrator->up();
        echo $ran === [] ? "Nothing to migrate.\n" : 'Applied: ' . implode(', ', $ran) . "\n";
        break;
    case 'down':
        $id = $migrator->down();
        echo $id === null ? "Nothing to roll back.\n" : "Rolled back: {$id}\n";
        break;
    case 'status':
        $applied = $migrator->applied();
        echo $applied === [] ? "No migrations applied.\n" : implode("\n", $applied) . "\n";
        break;
    default:
        fwrite(STDERR, "Unknown command: {$command}\n");
        exit(2);
}
