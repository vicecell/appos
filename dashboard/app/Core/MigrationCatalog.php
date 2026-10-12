<?php

declare(strict_types=1);

namespace ServerWatch\Core;

use RuntimeException;

/**
 * Discovers versioned migrations on disk and enforces that every migration is
 * reversible: NNNN_name.up.sql must have a matching NNNN_name.down.sql.
 */
final class MigrationCatalog
{
    private const PATTERN = '/^(\d{4})_([a-z0-9_]+)\.(up|down)\.sql$/';

    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @return array<string, array{up: string, down: string}> keyed by "NNNN_name", ordered ascending
     */
    public function all(): array
    {
        if (!is_dir($this->directory)) {
            throw new RuntimeException("Migration directory not found: {$this->directory}");
        }

        $found = [];
        foreach (scandir($this->directory) ?: [] as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                continue;
            }
            if (!preg_match(self::PATTERN, $file, $m)) {
                throw new RuntimeException("Invalid migration filename: {$file}");
            }
            $found[$m[1] . '_' . $m[2]][$m[3]] = $this->directory . DIRECTORY_SEPARATOR . $file;
        }

        ksort($found);
        $versions = [];
        foreach ($found as $id => $paths) {
            if (!isset($paths['up'], $paths['down'])) {
                throw new RuntimeException("Migration {$id} is not reversible: need both .up.sql and .down.sql");
            }
            $prefix = substr($id, 0, 4);
            if (isset($versions[$prefix])) {
                throw new RuntimeException("Duplicate migration version {$prefix}");
            }
            $versions[$prefix] = $id;
        }

        $result = [];
        foreach ($found as $id => $paths) {
            $result[$id] = ['up' => $paths['up'], 'down' => $paths['down']];
        }
        return $result;
    }
}
