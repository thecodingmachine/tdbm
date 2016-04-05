<?php

namespace Mouf\Database\TDBM;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;

/**
 * This class is used to analyze the schema and return valuable information / hints.
 */
class TDBMSchemaAnalyzer
{
    private $connection;

    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var string
     */
    private $cachePrefix;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var SchemaAnalyzer
     */
    private $schemaAnalyzer;

    /**
     * @param Connection     $connection     The DBAL DB connection to use
     * @param Cache          $cache          A cache service to be used
     * @param SchemaAnalyzer $schemaAnalyzer The schema analyzer that will be used to find shortest paths...
     *                                       Will be automatically created if not passed.
     */
    public function __construct(Connection $connection, Cache $cache, SchemaAnalyzer $schemaAnalyzer)
    {
        $this->connection = $connection;
        $this->cache = $cache;
        $this->schemaAnalyzer = $schemaAnalyzer;
    }

    /**
     * Returns a unique ID for the current connection. Useful for namespacing cache entries in the current connection.
     *
     * @return string
     */
    public function getCachePrefix()
    {
        if ($this->cachePrefix === null) {
            $this->cachePrefix = hash('md4', $this->connection->getHost().'-'.$this->connection->getPort().'-'.$this->connection->getDatabase().'-'.$this->connection->getDriver()->getName());
        }

        return $this->cachePrefix;
    }

    /**
     * Returns the (cached) schema.
     *
     * @return Schema
     */
    public function getSchema()
    {
        if ($this->schema === null) {
            $cacheKey = $this->getCachePrefix().'_schema';
            if ($this->cache->contains($cacheKey)) {
                $this->schema = $this->cache->fetch($cacheKey);
            } else {
                $this->schema = $this->connection->getSchemaManager()->createSchema();
                $this->cache->save($cacheKey, $this->schema);
            }
        }

        return $this->schema;
    }

    /**
     * Returns the list of pivot tables linked to table $tableName.
     *
     * @param string $tableName
     *
     * @return array|string[]
     */
    public function getPivotTableLinkedToTable($tableName)
    {
        $cacheKey = $this->getCachePrefix().'_pivottables_link_'.$tableName;
        if ($this->cache->contains($cacheKey)) {
            return $this->cache->fetch($cacheKey);
        }

        $pivotTables = [];

        $junctionTables = $this->schemaAnalyzer->detectJunctionTables(true);
        foreach ($junctionTables as $table) {
            $fks = $table->getForeignKeys();
            foreach ($fks as $fk) {
                if ($fk->getForeignTableName() == $tableName) {
                    $pivotTables[] = $table->getName();
                    break;
                }
            }
        }

        $this->cache->save($cacheKey, $pivotTables);

        return $pivotTables;
    }

    /**
     * Returns the list of foreign keys pointing to the table represented by this bean, excluding foreign keys
     * from junction tables and from inheritance.
     *
     * @return ForeignKeyConstraint[]
     */
    public function getIncomingForeignKeys($tableName)
    {
        $junctionTables = $this->schemaAnalyzer->detectJunctionTables(true);
        $junctionTableNames = array_map(function (Table $table) { return $table->getName(); }, $junctionTables);
        $childrenRelationships = $this->schemaAnalyzer->getChildrenRelationships($tableName);

        $fks = [];
        foreach ($this->getSchema()->getTables() as $table) {
            foreach ($table->getForeignKeys() as $fk) {
                if ($fk->getForeignTableName() === $tableName) {
                    if (in_array($fk->getLocalTableName(), $junctionTableNames)) {
                        continue;
                    }
                    foreach ($childrenRelationships as $childFk) {
                        if ($fk->getLocalTableName() === $childFk->getLocalTableName() && $fk->getLocalColumns() === $childFk->getLocalColumns()) {
                            continue 2;
                        }
                    }
                    $fks[] = $fk;
                }
            }
        }

        return $fks;
    }
}
