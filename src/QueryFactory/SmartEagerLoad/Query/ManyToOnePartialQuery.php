<?php


namespace TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\Query;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\ManyToOneDataLoader;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\StorageNode;

class ManyToOnePartialQuery implements PartialQuery
{
    /**
     * @var string
     */
    private $queryFrom;
    /**
     * @var string
     */
    private $mainTable;
    /**
     * @var StorageNode
     */
    private $storageNode;
    /**
     * @var string
     */
    private $key;
    /**
     * @var string
     */
    private $pk;

    public function __construct(PartialQuery $partialQuery, string $originTableName, string $tableName, string $pk, string $columnName)
    {
        // TODO: move this in a separate function. The constructor is called for every bean.
        $mysqlPlatform = new MySqlPlatform();
        $this->queryFrom = 'FROM ' .$mysqlPlatform->quoteIdentifier($tableName).
            ' WHERE ' .$mysqlPlatform->quoteIdentifier($tableName).'.'.$mysqlPlatform->quoteIdentifier($pk).' IN '.
            '(SELECT '.$mysqlPlatform->quoteIdentifier($originTableName).'.'.$mysqlPlatform->quoteIdentifier($columnName).' '.$partialQuery->getQueryFrom().')';
        $this->mainTable = $tableName;
        $this->storageNode = $partialQuery->getStorageNode();
        $this->key = $partialQuery->getKey().'__'.$columnName;
        $this->pk = $pk;
    }

    /**
     * Returns the SQL of the query, starting at the FROM keyword.
     */
    public function getQueryFrom(): string
    {
        return $this->queryFrom;
    }

    /**
     * Returns the name of the main table (main objects returned by this query)
     */
    public function getMainTable(): string
    {
        return $this->mainTable;
    }

    /**
     * Returns the object in charge of storing the dataloader associated to this query.
     */
    public function getStorageNode(): StorageNode
    {
        return $this->storageNode;
    }

    /**
     * Returns a key representing the "path" to this query. This is meant to be used as a cache key.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Registers a dataloader for this query, if needed.
     */
    public function registerDataLoader(Connection $connection): void
    {
        if ($this->storageNode->hasManyToOneDataLoader($this->key)) {
            return;
        }

        $mysqlPlatform = new MySqlPlatform();
        $sql = 'SELECT DISTINCT ' .$mysqlPlatform->quoteIdentifier($this->mainTable).'.* '.$this->queryFrom;

        $this->storageNode->setManyToOneDataLoader($this->key, new ManyToOneDataLoader($connection, $sql, $this->pk));
    }
}
