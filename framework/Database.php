<?php

namespace Mini;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(bool $withoutDatabase = false): PDO
    {
        if (!$withoutDatabase && self::$pdo) {
            return self::$pdo;
        }

        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');
        $database = env('DB_DATABASE', 'mini_app');
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ($withoutDatabase ? '' : ';dbname=' . $database) . ';charset=utf8mb4';
        $pdo = new PDO($dsn, env('DB_USERNAME', 'root'), env('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        if (!$withoutDatabase) {
            self::$pdo = $pdo;
        }
        return $pdo;
    }

    public static function statement(string $sql, array $bindings = []): \PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($bindings);
        return $statement;
    }

    public static function fetch(string $sql, array $bindings = []): ?array
    {
        $row = self::statement($sql, $bindings)->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $bindings = []): array
    {
        return self::statement($sql, $bindings)->fetchAll();
    }

    public static function fetchValue(string $sql, array $bindings = []): mixed
    {
        return self::statement($sql, $bindings)->fetchColumn();
    }

    public static function fetchPairs(string $sql, array $bindings = []): array
    {
        return self::statement($sql, $bindings)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function insert(string $table, array $data): int
    {
        self::identifier($table);
        if ($data === []) {
            throw new \InvalidArgumentException('Insert data cannot be empty.');
        }
        $columns = array_keys($data);
        foreach ($columns as $column) {
            self::identifier($column);
        }
        $quoted = array_map(fn ($column) => "`{$column}`", $columns);
        $sql = "INSERT INTO `{$table}` (" . implode(', ', $quoted) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        self::statement($sql, array_values($data));
        return (int) self::connection()->lastInsertId();
    }

    public static function update(string $table, int $id, array $data): void
    {
        self::identifier($table);
        if ($data === []) {
            throw new \InvalidArgumentException('Update data cannot be empty.');
        }
        foreach (array_keys($data) as $column) {
            self::identifier($column);
        }
        $sets = array_map(fn ($column) => "`{$column}` = ?", array_keys($data));
        self::statement("UPDATE `{$table}` SET " . implode(', ', $sets) . ' WHERE id = ?', [...array_values($data), $id]);
    }

    private static function identifier(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid SQL identifier.');
        }
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

