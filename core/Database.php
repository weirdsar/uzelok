<?php

declare(strict_types=1);

namespace Uzelok\Core;

use PDO;
use PDOStatement;

final class Database
{
    private static ?self $instance = null;

    private readonly PDO $pdo;

    private function __construct(string $dbPath)
    {
        $this->pdo = new PDO(
            'sqlite:' . $dbPath,
            options: [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');
    }

    public static function getInstance(string $dbPath): self
    {
        return self::$instance ??= new self($dbPath);
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param array<string, mixed> $params Named parameters only (e.g. [':id' => 1])
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }
}
