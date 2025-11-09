<?php

namespace Core\Facades;

/**
 * Class DB
 *
 * The DB facade provides a simple, expressive interface to interact
 * with the database layer. It acts as a static proxy to the
 * underlying 
 * @see \Core\Database\DatabaseManager and
 * @see \Core\Database\Connection instances.
 *
 * This allows global access to database operations such as queries,
 *
 * @method static \Core\Database\QueryBuilder table(string $table)
 *     Get a new query builder instance for the given table.
 *
 * @method static \Core\Contracts\Database\ConnectionInterface connection(?string $name = null)
 *     Get a database connection by name or the default connection.
 *
 * @method static array select(string $query, array $bindings = [])
 *     Execute a select statement and return all results.
 *
 * @method static bool insert(string $query, array $bindings = [])
 *     Execute an insert statement.
 *
 * @method static int update(string $query, array $bindings = [])
 *     Execute an update statement and return affected rows.
 *
 * @method static int delete(string $query, array $bindings = [])
 *     Execute a delete statement and return affected rows.
 *
 * @method static bool statement(string $query, array $bindings = [])
 *     Execute a general statement that doesn’t return results.
 *
 * @method static int affectingStatement(string $query, array $bindings = [])
 *     Execute an SQL statement that affects rows (update/delete).
 *
 * @method static bool unprepared(string $query)
 *     Execute a raw SQL statement without bindings.
 *
 * @method static bool beginTransaction()
 *     Start a new database transaction.
 *
 * @method static bool commit()
 *     Commit the active database transaction.
 *
 * @method static bool rollBack()
 *     Roll back the last database transaction.
 *
 * @method static bool inTransaction()
 *     Determine if a transaction is currently active.
 *
 * @method static string|false lastInsertId()
 *     Get the last inserted ID from the database connection.
 *
 * @method static array storedProcedure(string $procedure, array $params = [])
 *     Call a stored procedure and return its results.
 *
 * @method static \Core\Database\QueryBuilder query()
 *     Get a new query builder instance.
 *
 * @package Core\Facades
 */
class DB extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'db';
    }
}
