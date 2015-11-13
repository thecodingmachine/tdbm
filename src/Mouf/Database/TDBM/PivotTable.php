<?php


namespace Mouf\Database\TDBM;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

/**
 * An object representing a cache to a pivot table.
 * Useful to remember all objects that have been stored.
 */
class PivotTable
{
    /**
     * @var string
     */
    private $pivotTableName;

    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $table1;

    /**
     * @var string
     */
    private $table2;

    /**
     * @var \SplObjectStorage
     */
    private $table1Relationships;

    /**
     * @var \SplObjectStorage
     */
    private $table2Relationships;

    /**
     * @param string $pivotTableName
     * @param Schema $schema
     * @param Connection $connection
     */
    public function __construct($pivotTableName, Schema $schema, Connection $connection)
    {
        $this->pivotTableName = $pivotTableName;
        $this->schema = $schema;
        $this->connection = $connection;
        $fks = array_values($this->schema->getTable($pivotTableName)->getForeignKeys());
        $this->table1 = $fks[0]->getForeignTableName();
        $this->table2 = $fks[1]->getForeignTableName();
        $this->table1Relationships = new \SplObjectStorage();
        $this->table2Relationships = new \SplObjectStorage();
    }

    public function addRelationship(AbstractTDBMObject $bean1, AbstractTDBMObject $bean2, $status) {
        $tables1 = array_map(function(DbRow $dbRow) { return $dbRow->_getDbTableName(); }, $bean1->_getDbRows());
        $tables2 = array_map(function(DbRow $dbRow) { return $dbRow->_getDbTableName(); }, $bean2->_getDbRows());

        if (in_array($this->table1, $tables1) && in_array($this->table2, $tables2)) {
            // Do nothing
        } elseif (in_array($this->table2, $tables1) && in_array($this->table1, $tables2)) {
            $beanTmp = $bean1;
            $bean1 = $bean2;
            $bean2 = $beanTmp;
        } else {
            throw new TDBMException("Unexpected beans type in addRelationship. Awaiting beans from table {$this->table1} and {$this->table2}");
        }

        if (isset($this->table1Relationships[$bean1])) {
            $innerSplObj = $this->table1Relationships[$bean1];
        } else {
            $innerSplObj = new \SplObjectStorage();
            $this->table1Relationships->attach($bean1, $innerSplObj);
        }
        $innerSplObj->attach($bean2, $status);

        // Same thing, opposite way
        if (isset($this->table2Relationships[$bean2])) {
            $innerSplObj = $this->table2Relationships[$bean2];
        } else {
            $innerSplObj = new \SplObjectStorage();
            $this->table2Relationships->attach($bean2, $innerSplObj);
        }
        $innerSplObj->attach($bean1, $status);
    }

    /**
     * Returns an SplObjectStorage containing the list of beans along their status associated to $bean.
     * @param AbstractTDBMObject $bean
     * @return \SplObjectStorage
     * @throws TDBMException
     */
    public function getRelationShips(AbstractTDBMObject $bean) {
        $tables = array_map(function(DbRow $dbRow) { return $dbRow->_getDbTableName(); }, $bean->_getDbRows());

        if (in_array($this->table1, $tables)) {
            $splObjStorage = $this->table1Relationships;
        } elseif (in_array($this->table2, $tables)) {
            $splObjStorage = $this->table2Relationships;
        } else {
            throw new TDBMException("Unexpected beans type in getRelationShips. Awaiting beans from table {$this->table1} and {$this->table2}");
        }

        return $splObjStorage[$bean];
    }


}
