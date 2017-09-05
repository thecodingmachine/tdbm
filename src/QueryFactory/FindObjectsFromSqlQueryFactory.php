<?php

namespace TheCodingMachine\TDBM\QueryFactory;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use TheCodingMachine\TDBM\OrderByAnalyzer;
use TheCodingMachine\TDBM\TDBMException;
use TheCodingMachine\TDBM\TDBMService;

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
        // We quote in MySQL because of MagicQuery that will be applied.
        $mySqlPlatform = new MySqlPlatform();

        $columnsList = null;

        $allFetchedTables = $this->tdbmService->_getRelatedTablesByInheritance($this->mainTable);

        list($columnDescList, $columnsList, $orderString) = $this->getColumnsList($this->mainTable, [], $this->orderBy);

        $sql = 'SELECT DISTINCT '.implode(', ', $columnsList).' FROM '.$this->from;

        // Let's compute the COUNT.
        $pkColumnNames = $this->tdbmService->getPrimaryKeyColumns($this->mainTable);
        $pkColumnNames = array_map(function ($pkColumn) use ($mySqlPlatform) {
            return $mySqlPlatform->quoteIdentifier($this->mainTable).'.'.$mySqlPlatform->quoteIdentifier($pkColumn);
        }, $pkColumnNames);

        $countSql = 'SELECT COUNT(DISTINCT '.implode(', ', $pkColumnNames).') FROM '.$this->from;

        // Add joins on inherited tables if necessary
        if (count($allFetchedTables) > 1) {
            $joinSql = '';
            $parentFks = $this->getParentRelationshipForeignKeys($this->mainTable);
            foreach ($parentFks as $fk) {
                $joinSql .= sprintf(' JOIN %s ON (%s.%s = %s.%s)',
                    $mySqlPlatform->quoteIdentifier($fk->getForeignTableName()),
                    $mySqlPlatform->quoteIdentifier($fk->getLocalTableName()),
                    $mySqlPlatform->quoteIdentifier($fk->getUnquotedLocalColumns()[0]),
                    $mySqlPlatform->quoteIdentifier($fk->getForeignTableName()),
                    $mySqlPlatform->quoteIdentifier($fk->getUnquotedForeignColumns()[0])
                );
            }

            $childrenFks = $this->getChildrenRelationshipForeignKeys($this->mainTable);
            foreach ($childrenFks as $fk) {
                $joinSql .= sprintf(' LEFT JOIN %s ON (%s.%s = %s.%s)',
                    $mySqlPlatform->quoteIdentifier($fk->getLocalTableName()),
                    $mySqlPlatform->quoteIdentifier($fk->getForeignTableName()),
                    $mySqlPlatform->quoteIdentifier($fk->getUnquotedForeignColumns()[0]),
                    $mySqlPlatform->quoteIdentifier($fk->getLocalTableName()),
                    $mySqlPlatform->quoteIdentifier($fk->getUnquotedLocalColumns()[0])
                );
            }

            $sql .= $joinSql;
        }

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
