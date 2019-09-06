<?php


namespace TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Mouf\Database\MagicQuery;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\ManyToOneDataLoader;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\StorageNode;

class ManyToOnePartialQuery implements PartialQuery
{
    /**
     * @var string
     */
    private $mainTable;
    /**
     * @var string
     */
    private $key;
    /**
     * @var string
     */
    private $pk;
    /**
     * @var PartialQuery
     */
    private $partialQuery;
    /**
     * @var string
     */
    private $originTableName;
    /**
     * @var string
     */
    private $columnName;

    public function __construct(PartialQuery $partialQuery, string $originTableName, string $tableName, string $pk, string $columnName)
    {
        // TODO: move this in a separate function. The constructor is called for every bean.
        $this->partialQuery = $partialQuery;
        $this->mainTable = $tableName;
        $this->key = $partialQuery->getKey().'__'.$columnName;
        $this->pk = $pk;
        $this->originTableName = $originTableName;
        $this->columnName = $columnName;
    }

    /**
     * Returns the SQL of the query, starting at the FROM keyword.
     */
    public function getQueryFrom(): string
    {
        $mysqlPlatform = new MySqlPlatform();
        return 'FROM ' .$mysqlPlatform->quoteIdentifier($this->mainTable).
            ' WHERE ' .$mysqlPlatform->quoteIdentifier($this->mainTable).'.'.$mysqlPlatform->quoteIdentifier($this->pk).' IN '.
            '(SELECT '.$mysqlPlatform->quoteIdentifier($this->originTableName).'.'.$mysqlPlatform->quoteIdentifier($this->columnName).' '.$this->partialQuery->getQueryFrom().')';
    }

    /**
     * Returns the object in charge of storing the dataloader associated to this query.
     */
    public function getStorageNode(): StorageNode
    {
        return $this->partialQuery->getStorageNode();
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
        $storageNode = $this->getStorageNode();
        if ($storageNode->hasManyToOneDataLoader($this->key)) {
            return;
        }

        $mysqlPlatform = new MySqlPlatform();
        $sql = 'SELECT DISTINCT ' .$mysqlPlatform->quoteIdentifier($this->mainTable).'.* '.$this->getQueryFrom();

        if (!$connection->getDatabasePlatform() instanceof MySqlPlatform) {
            // We need to convert the query from MySQL dialect to something else
            $sql = $this->getMagicQuery()->buildPreparedStatement($sql);
        }

        $storageNode->setManyToOneDataLoader($this->key, new ManyToOneDataLoader($connection, $sql, $this->pk));
    }

    public function getMagicQuery(): MagicQuery
    {
        return $this->partialQuery->getMagicQuery();
    }
}
