<?php

declare(strict_types=1);

use Uzelok\Core\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__) . '/config/config.php';

$dbPath = $config['paths']['database'];
if (!is_string($dbPath)) {
    throw new RuntimeException('config paths.database must be a string');
}

$dbDir = dirname($dbPath);
if (!is_dir($dbDir) && !mkdir($dbDir, 0755, true) && !is_dir($dbDir)) {
    throw new RuntimeException('Cannot create database directory: ' . $dbDir);
}

$logsDir = $config['paths']['logs'] ?? '';
if (is_string($logsDir) && $logsDir !== '' && !is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

$pdo = Database::getInstance($dbPath)->getConnection();

$migrationFiles = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($migrationFiles, SORT_STRING);

foreach ($migrationFiles as $migrationFile) {
    $sql = file_get_contents($migrationFile);
    if ($sql === false) {
        throw new RuntimeException('Cannot read migration: ' . $migrationFile);
    }
    $statements = array_filter(
        array_map(trim(...), explode(';', $sql)),
        static fn (string $s): bool => $s !== ''
    );
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
        } catch (\PDOException $e) {
            if (stripos($e->getMessage(), 'duplicate column') === false) {
                throw $e;
            }
        }
    }
}

echo 'Database initialized successfully' . PHP_EOL;
