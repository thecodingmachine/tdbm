<?php


namespace TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\Query;

use Doctrine\DBAL\Connection;
use Mouf\Database\MagicQuery;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\StorageNode;

/**
 * Represents a subquery to be used in another query. You can get everything starting from "FROM" keyword.
 * The list of columns is not included in this object.
 */
interface PartialQuery
{
    /**
     * Returns the SQL of the query, starting at the FROM keyword.
     */
    public function getQueryFrom(): string;

    /**
     * Returns a key representing the "path" to this query. This is meant to be used as a cache key.
     */
    public function getKey(): string;

    /**
     * Registers a dataloader for this query, if needed.
     */
    public function registerDataLoader(Connection $connection): void;

    /**
     * Returns the object in charge of storing the dataloader associated to this query.
     */
    public function getStorageNode(): StorageNode;

    public function getMagicQuery(): MagicQuery;
}
