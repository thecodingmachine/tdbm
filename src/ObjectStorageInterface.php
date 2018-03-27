<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM;


/**
 * Object in charge of referencing all beans that have been fetched from the database.
 * If a bean is requested twice from TDBM, the ObjectStorage is used to "cache" the bean.
 * This interface has 2 implementations: StandardObjectStorage and WeakRefObjectStorage.
 *
 * @author David Negrier
 */
interface ObjectStorageInterface
{
    /**
     * Sets an object in the storage.
     *
     * @param string $tableName
     * @param string $id
     * @param DbRow $dbRow
     */
    public function set(string $tableName, $id, DbRow $dbRow): void;

    /**
     * Checks if an object is in the storage.
     *
     * @param string $tableName
     * @param string $id
     *
     * @return bool
     */
    public function has(string $tableName, $id): bool;

    /**
     * Returns an object from the storage (or null if no object is set).
     *
     * @param string $tableName
     * @param string $id
     *
     * @return DbRow|null
     */
    public function get(string $tableName, $id): ?DbRow;

    /**
     * Removes an object from the storage.
     *
     * @param string $tableName
     * @param string $id
     */
    public function remove(string $tableName, $id): void;

    /**
     * Applies the callback to all objects.
     *
     * @param callable $callback
     */
    public function apply(callable $callback): void;
}
