<?php

namespace Core\Database;

use Core\Contracts\Database\ConnectionInterface;
use Core\Exceptions\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;

class Connection implements ConnectionInterface
{
    protected PDO $pdo;
    protected array $config;
    protected int $transactionLevel = 0;
    protected ?string $name = null;

    public function __construct(array $config, ?string $name = null)
    {
        $this->config = $config;
        $this->name = $name;
        $this->createConnection();
    }

    protected function createConnection(): void
    {
        $driver = $this->config['driver'] ?? 'mysql';
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? null;
        $database = $this->config['database'] ?? null;
        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;
        $charset = $this->config['charset'] ?? 'utf8';
        $collation = $this->config['collation'] ?? 'utf8_general_ci';

        // Build DSN based on driver
        switch ($driver) {
            case 'pgsql':
                $port = $port ?? 5432;
                $sslmode = $this->config['sslmode'] ?? 'prefer';
                $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslmode}";
                break;

            case 'sqlsrv':
            case 'mssql':
                // SQL Server DSN does not use charset in DSN string
                $dsn = "{$driver}:Server={$host}," . ($port ?? 1433) . ";Database={$database}";
                break;

            case 'sqlite':
                $dsn = "sqlite:" . ($database ?? ':memory:');
                break;

            case 'mysql':
            default:
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
                break;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);

            // Set encoding depending on driver
            if ($driver === 'mysql') {
                $this->pdo->exec("SET NAMES '{$charset}' COLLATE '{$collation}'");
            } elseif ($driver === 'pgsql') {
                $this->pdo->exec("SET client_encoding TO '{$charset}'");
            }
        } catch (PDOException $e) {
            throw new DatabaseException("Connection failed ({$driver}): " . $e->getMessage(), 0, $e);
        }
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function select(string $query, array $bindings = []): array
    {
        $statement = $this->run($query, $bindings);
        return $statement->fetchAll();
    }

    public function insert(string $query, array $bindings = []): bool
    {
        return $this->statement($query, $bindings);
    }

    public function update(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    public function delete(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    public function statement(string $query, array $bindings = []): bool
    {
        return $this->run($query, $bindings)->rowCount() > 0;
    }

    public function affectingStatement(string $query, array $bindings = []): int
    {
        return $this->run($query, $bindings)->rowCount();
    }

    public function unprepared(string $query): bool
    {
        try {
            return $this->pdo->exec($query) !== false;
        } catch (PDOException $e) {
            throw new DatabaseException("Query failed: " . $e->getMessage(), 0, $e);
        }
    }

    public function prepare(string $query): PDOStatement
    {
        try {
            return $this->pdo->prepare($query);
        } catch (PDOException $e) {
            throw new DatabaseException("Failed to prepare statement: " . $e->getMessage(), 0, $e);
        }
    }


    protected function run(string $query, array $bindings = []): PDOStatement
    {
        try {
            $statement = $this->pdo->prepare($query);

            foreach ($bindings as $key => $value) {
                $statement->bindValue(
                    is_string($key) ? $key : $key + 1,
                    $value,
                    $this->getPdoType($value)
                );
            }

            $statement->execute();

            return $statement;
        } catch (PDOException $e) {
            throw new DatabaseException("Query failed: " . $e->getMessage() . " SQL: {$query}", 0, $e);
        }
    }

    protected function getPdoType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    public function beginTransaction(): bool
    {
        if ($this->transactionLevel === 0) {
            $result = $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT trans{$this->transactionLevel}");
            $result = true;
        }

        $this->transactionLevel++;
        return $result;
    }

    public function commit(): bool
    {
        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            return $this->pdo->commit();
        }

        return true;
    }

    public function rollBack(): bool
    {
        if ($this->transactionLevel === 0) {
            throw new DatabaseException("No active transaction");
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            return $this->pdo->rollBack();
        }

        $this->pdo->exec("ROLLBACK TO SAVEPOINT trans{$this->transactionLevel}");
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    public function lastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Store procedure method
     */
    public function storedProcedure(string $procedure, array $params = []): array
    {
        $driver = $this->config['driver'] ?? 'mysql';
        $placeholders = implode(',', array_fill(0, count($params), '?'));

        switch ($driver) {
            case 'sqlsrv':
            case 'mssql':
                $sql = "EXEC {$procedure}" . ($placeholders ? " {$placeholders}" : '');
                break;

            case 'mysql':
            default:
                $sql = "CALL {$procedure}" . ($placeholders ? "({$placeholders})" : '()');
                break;
        }

        return $this->select($sql, $params);
    }


    /**
     * Create a new query builder instance for the given table
     * 
     * @param string $table The table name
     * @param Model|null $model Optional model instance for configuration
     * @return QueryBuilder
     */
    public function table(string $table, ?Model $model = null): QueryBuilder
    {
        $query = new QueryBuilder($this, $model);
        return $query->table($table);
    }
}