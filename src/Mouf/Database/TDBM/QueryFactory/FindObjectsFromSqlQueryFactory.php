<?php

namespace Mouf\Database\TDBM\QueryFactory;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use Mouf\Database\TDBM\OrderByAnalyzer;
use Mouf\Database\TDBM\TDBMException;
use Mouf\Database\TDBM\TDBMService;

/**
 * This class is in charge of creating the MagicQuery SQL based on parameters passed to findObjectsFromSql method.
 */
class FindObjectsFromSqlQueryFactory extends AbstractQueryFactory
{
    private $mainTable;
    private $from;
    private $filterString;
    private $cache;
    private $cachePrefix;

    public function __construct(string $mainTable, string $from, $filterString, $orderBy, TDBMService $tdbmService, Schema $schema, OrderByAnalyzer $orderByAnalyzer, SchemaAnalyzer $schemaAnalyzer, Cache $cache, string $cachePrefix)
    {
        parent::__construct($tdbmService, $schema, $orderByAnalyzer, $orderBy);
        $this->mainTable = $mainTable;
        $this->from = $from;
        $this->filterString = $filterString;
        $this->schemaAnalyzer = $schemaAnalyzer;
        $this->cache = $cache;
        $this->cachePrefix = $cachePrefix;
    }

    protected function compute()
    {
        $connection = $this->tdbmService->getConnection();

        $columnsList = null;

        $allFetchedTables = $this->tdbmService->_getRelatedTablesByInheritance($this->mainTable);

        $columnDescList = [];

        $tableGroupName = $this->getTableGroupName($allFetchedTables);

        foreach ($this->schema->getTable($this->mainTable)->getColumns() as $column) {
            $columnName = $column->getName();
            $columnDescList[] = [
                'as' => $columnName,
                'table' => $this->mainTable,
                'column' => $columnName,
                'type' => $column->getType(),
                'tableGroup' => $tableGroupName,
            ];
        }

        $sql = 'SELECT DISTINCT '.implode(', ', array_map(function ($columnDesc) {
            return $this->tdbmService->getConnection()->quoteIdentifier($this->mainTable).'.'.$this->tdbmService->getConnection()->quoteIdentifier($columnDesc['column']);
        }, $columnDescList)).' FROM '.$this->from;

        if (count($allFetchedTables) > 1 || $this->orderBy) {
            list($columnDescList, $columnsList, $orderString) = $this->getColumnsList($this->mainTable, [], $this->orderBy);
        }

        // Let's compute the COUNT.
        $pkColumnNames = $this->schema->getTable($this->mainTable)->getPrimaryKeyColumns();
        $pkColumnNames = array_map(function ($pkColumn) {
            return $this->tdbmService->getConnection()->quoteIdentifier($this->mainTable).'.'.$this->tdbmService->getConnection()->quoteIdentifier($pkColumn);
        }, $pkColumnNames);

        $countSql = 'SELECT COUNT(DISTINCT '.implode(', ', $pkColumnNames).') FROM '.$this->from;

        if (!empty($this->filterString)) {
            $sql .= ' WHERE '.$this->filterString;
            $countSql .= ' WHERE '.$this->filterString;
        }

        if (!empty($orderString)) {
            $sql .= ' ORDER BY '.$orderString;
        }

        if (stripos($countSql, 'GROUP BY') !== false) {
            throw new TDBMException('Unsupported use of GROUP BY in SQL request.');
        }

        if ($columnsList !== null) {
            $joinSql = '';
            $parentFks = $this->getParentRelationshipForeignKeys($this->mainTable);
            foreach ($parentFks as $fk) {
                $joinSql .= sprintf(' JOIN %s ON (%s.%s = %s.%s)',
                    $connection->quoteIdentifier($fk->getForeignTableName()),
                    $connection->quoteIdentifier($fk->getLocalTableName()),
                    $connection->quoteIdentifier($fk->getLocalColumns()[0]),
                    $connection->quoteIdentifier($fk->getForeignTableName()),
                    $connection->quoteIdentifier($fk->getForeignColumns()[0])
                );
            }

            $childrenFks = $this->getChildrenRelationshipForeignKeys($this->mainTable);
            foreach ($childrenFks as $fk) {
                $joinSql .= sprintf(' LEFT JOIN %s ON (%s.%s = %s.%s)',
                    $connection->quoteIdentifier($fk->getLocalTableName()),
                    $connection->quoteIdentifier($fk->getForeignTableName()),
                    $connection->quoteIdentifier($fk->getForeignColumns()[0]),
                    $connection->quoteIdentifier($fk->getLocalTableName()),
                    $connection->quoteIdentifier($fk->getLocalColumns()[0])
                );
            }

            $sql = 'SELECT '.implode(', ', $columnsList).' FROM ('.$sql.') AS '.$this->mainTable.' '.$joinSql;
            if (!empty($orderString)) {
                $sql .= ' ORDER BY '.$orderString;
            }
        }

        $this->magicSql = $sql;
        $this->magicSqlCount = $countSql;
        $this->columnDescList = $columnDescList;
    }

    /**
     * @param string $tableName
     *
     * @return ForeignKeyConstraint[]
     */
    private function getParentRelationshipForeignKeys($tableName)
    {
        return $this->fromCache($this->cachePrefix.'_parentrelationshipfks_'.$tableName, function () use ($tableName) {
            return $this->getParentRelationshipForeignKeysWithoutCache($tableName);
        });
    }

    /**
     * @param string $tableName
     *
     * @return ForeignKeyConstraint[]
     */
    private function getParentRelationshipForeignKeysWithoutCache($tableName)
    {
        $parentFks = [];
        $currentTable = $tableName;
        while ($currentFk = $this->schemaAnalyzer->getParentRelationship($currentTable)) {
            $currentTable = $currentFk->getForeignTableName();
            $parentFks[] = $currentFk;
        }

        return $parentFks;
    }

    /**
     * @param string $tableName
     *
     * @return ForeignKeyConstraint[]
     */
    private function getChildrenRelationshipForeignKeys(string $tableName) : array
    {
        return $this->fromCache($this->cachePrefix.'_childrenrelationshipfks_'.$tableName, function () use ($tableName) {
            return $this->getChildrenRelationshipForeignKeysWithoutCache($tableName);
        });
    }

    /**
     * @param string $tableName
     *
     * @return ForeignKeyConstraint[]
     */
    private function getChildrenRelationshipForeignKeysWithoutCache(string $tableName) : array
    {
        $children = $this->schemaAnalyzer->getChildrenRelationships($tableName);

        if (!empty($children)) {
            $fksTables = array_map(function (ForeignKeyConstraint $fk) {
                return $this->getChildrenRelationshipForeignKeys($fk->getLocalTableName());
            }, $children);

            $fks = array_merge($children, call_user_func_array('array_merge', $fksTables));

            return $fks;
        } else {
            return [];
        }
    }

    /**
     * Returns an item from cache or computes it using $closure and puts it in cache.
     *
     * @param string   $key
     * @param callable $closure
     *
     * @return mixed
     */
    protected function fromCache(string $key, callable $closure)
    {
        $item = $this->cache->fetch($key);
        if ($item === false) {
            $item = $closure();
            $this->cache->save($key, $item);
        }

        return $item;
    }
}
