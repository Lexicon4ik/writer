<?php declare(strict_types=1);

namespace NewsBot\Core;

use PDO;
use PDOStatement;
use PDOException;

/**
 * PDO wrapper with singleton pattern.
 * UTF8MB4, exceptions mode, fetch assoc, persistent connections.
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Get PDO instance (singleton).
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        }

        return self::$instance;
    }

    /**
     * Execute a query with parameters.
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Alias for query() - for readability with raw SQL (INSERT ON DUPLICATE KEY, etc.)
     */
    public static function execute(string $sql, array $params = []): PDOStatement
    {
        return self::query($sql, $params);
    }

    /**
     * Fetch a single row.
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Fetch all rows.
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert a row and return last insert ID.
     */
    public static function insert(string $table, array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Cannot insert empty data');
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        self::query($sql, array_values($data));
        return (int)self::getInstance()->lastInsertId();
    }

    /**
     * Insert or update on duplicate key.
     * Returns: ['inserted' => bool, 'id' => int]
     * - inserted=true: new row created, id=lastInsertId
     * - inserted=false: existing row found, id=0 (caller should SELECT)
     */
    public static function insertOrIgnore(string $table, array $data): array
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Cannot insert empty data');
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT IGNORE INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $stmt = self::query($sql, array_values($data));
        $affectedRows = $stmt->rowCount();

        if ($affectedRows > 0) {
            return ['inserted' => true, 'id' => (int)self::getInstance()->lastInsertId()];
        }

        // Row already exists (duplicate key was ignored)
        return ['inserted' => false, 'id' => 0];
    }

    /**
     * Update rows and return affected count.
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Cannot update with empty data');
        }

        $setParts = [];
        $params = [];
        foreach ($data as $column => $value) {
            $setParts[] = "`{$column}` = ?";
            $params[] = $value;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $setParts),
            $where
        );

        $params = array_merge($params, $whereParams);
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete rows and return affected count.
     */
    public static function delete(string $table, string $where, array $whereParams = []): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);
        $stmt = self::query($sql, $whereParams);
        return $stmt->rowCount();
    }

    /**
     * Begin a transaction.
     */
    public static function beginTransaction(): void
    {
        self::getInstance()->beginTransaction();
    }

    /**
     * Commit the current transaction.
     */
    public static function commit(): void
    {
        self::getInstance()->commit();
    }

    /**
     * Rollback the current transaction.
     */
    public static function rollback(): void
    {
        self::getInstance()->rollBack();
    }

    /**
     * Check if currently in a transaction.
     */
    public static function inTransaction(): bool
    {
        return self::getInstance()->inTransaction();
    }

    /**
     * Reset connection (for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
