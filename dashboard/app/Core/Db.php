<?php

declare(strict_types=1);

namespace ServerWatch\Core;

use PDO;

/**
 * Lazy PDO holder so routes that need no database (e.g. /health) work without one.
 */
final class Db
{
    private ?PDO $pdo = null;

    /**
     * @param \Closure(): PDO $factory
     */
    public function __construct(private readonly \Closure $factory)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= ($this->factory)();
    }

    /**
     * @param \Closure(string): (string|false) $getenv
     */
    public static function fromEnv(\Closure $getenv): self
    {
        return new self(static function () use ($getenv): PDO {
            $need = static function (string $key) use ($getenv): string {
                $value = $getenv($key);
                if ($value === false || $value === '') {
                    throw new \RuntimeException("Missing required environment variable {$key}");
                }
                return $value;
            };
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $getenv('DB_HOST') ?: '127.0.0.1',
                $getenv('DB_PORT') ?: '3306',
                $need('DB_NAME'),
            );
            $pdo = new PDO($dsn, $need('DB_USER'), $need('DB_PASSWORD'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET time_zone = '+00:00'");
            return $pdo;
        });
    }
}
