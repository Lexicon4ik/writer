<?php declare(strict_types=1);

namespace NewsBot\Models;

use NewsBot\Core\Database;

/**
 * Abstract base model with CRUD operations.
 */
abstract class BaseModel
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Magic getter for model properties.
     */
    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Magic setter for model properties.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * Magic isset for model properties.
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Convert model to array.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Find by primary key.
     */
    public static function find(int $id): ?static
    {
        $row = Database::fetchOne(
            "SELECT * FROM `" . static::$table . "` WHERE `" . static::$primaryKey . "` = ?",
            [$id]
        );

        return $row ? new static($row) : null;
    }

    /**
     * Find by specific field.
     */
    public static function findBy(string $field, mixed $value): ?static
    {
        $row = Database::fetchOne(
            "SELECT * FROM `" . static::$table . "` WHERE `{$field}` = ?",
            [$value]
        );

        return $row ? new static($row) : null;
    }

    /**
     * Find all matching records.
     */
    public static function all(
        string $where = '1=1',
        array $params = [],
        string $orderBy = 'id ASC',
        int $limit = 1000
    ): array {
        $sql = "SELECT * FROM `" . static::$table . "` WHERE {$where} ORDER BY {$orderBy} LIMIT {$limit}";
        $rows = Database::fetchAll($sql, $params);

        return array_map(fn($row) => new static($row), $rows);
    }

    /**
     * Select specific columns (for lists without heavy fields like MEDIUMTEXT).
     *
     * @param string $columns Comma-separated columns: 'id, url, status'
     * @return array Array of associative arrays (NOT model objects)
     */
    public static function select(
        string $columns,
        string $where = '1=1',
        array $params = [],
        string $orderBy = 'id ASC',
        int $limit = 1000
    ): array {
        $sql = "SELECT {$columns} FROM `" . static::$table . "` WHERE {$where} ORDER BY {$orderBy} LIMIT {$limit}";
        return Database::fetchAll($sql, $params);
    }

    /**
     * Create a new record.
     */
    public static function create(array $data): static
    {
        $id = Database::insert(static::$table, $data);
        $data[static::$primaryKey] = $id;
        return new static($data);
    }

    /**
     * Update a record by ID.
     */
    public static function update(int $id, array $data): bool
    {
        $affected = Database::update(
            static::$table,
            $data,
            static::$primaryKey . ' = ?',
            [$id]
        );

        return $affected > 0;
    }

    /**
     * Delete a record by ID.
     */
    public static function delete(int $id): bool
    {
        $affected = Database::delete(
            static::$table,
            static::$primaryKey . ' = ?',
            [$id]
        );

        return $affected > 0;
    }

    /**
     * Count records.
     */
    public static function count(string $where = '1=1', array $params = []): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM `" . static::$table . "` WHERE {$where}",
            $params
        );

        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Check if record exists.
     */
    public static function exists(string $where, array $params = []): bool
    {
        return self::count($where, $params) > 0;
    }

    /**
     * Get the model's ID.
     */
    public function getId(): int
    {
        return (int)($this->data[static::$primaryKey] ?? 0);
    }

    /**
     * Save changes to the model.
     */
    public function save(): bool
    {
        $id = $this->getId();
        if ($id > 0) {
            $data = $this->data;
            unset($data[static::$primaryKey]);
            return static::update($id, $data);
        }
        return false;
    }

    /**
     * Refresh model data from database.
     */
    public function refresh(): void
    {
        $fresh = static::find($this->getId());
        if ($fresh) {
            $this->data = $fresh->toArray();
        }
    }
}
