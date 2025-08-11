<?php

namespace GroupScholar\CycleCoordinator;

use PDO;
use RuntimeException;

class Database
{
    private PDO $pdo;

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function fromEnvironment(): self
    {
        $databaseUrl = getenv('DATABASE_URL');
        if (!$databaseUrl) {
            throw new RuntimeException('DATABASE_URL is not set.');
        }

        $parts = parse_url($databaseUrl);
        if ($parts === false || !isset($parts['host'])) {
            throw new RuntimeException('DATABASE_URL is invalid.');
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? 5432;
        $user = isset($parts['user']) ? urldecode($parts['user']) : '';
        $pass = isset($parts['pass']) ? urldecode($parts['pass']) : '';
        $dbName = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

        if ($dbName === '') {
            throw new RuntimeException('DATABASE_URL is missing a database name.');
        }

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbName}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return new self($pdo);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
