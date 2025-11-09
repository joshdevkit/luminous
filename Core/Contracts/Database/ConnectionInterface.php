<?php

namespace Core\Contracts\Database;

use PDO;

interface ConnectionInterface
{
    public function getPdo(): PDO;
    public function select(string $query, array $bindings = []): array;
    public function insert(string $query, array $bindings = []): bool;
    public function update(string $query, array $bindings = []): int;
    public function delete(string $query, array $bindings = []): int;
    public function statement(string $query, array $bindings = []): bool;
    public function affectingStatement(string $query, array $bindings = []): int;
    public function prepare(string $query): \PDOStatement;
    public function unprepared(string $query): bool;
    public function beginTransaction(): bool;
    public function lastInsertId(): string|false;
    public function commit(): bool;
    public function rollBack(): bool;
    public function inTransaction(): bool;
    public function table(string $table): \Core\Database\QueryBuilder;
}