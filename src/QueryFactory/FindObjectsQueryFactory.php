<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\QueryFactory;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Schema;
use Mouf\Database\MagicQuery;
use TheCodingMachine\TDBM\InnerResultArray;
use TheCodingMachine\TDBM\OrderByAnalyzer;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\PartialQueryFactory;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\Query\PartialQuery;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\Query\StaticPartialQuery;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\StorageNode;
use TheCodingMachine\TDBM\TDBMService;
use function implode;

/**
 * This class is in charge of creating the MagicQuery SQL based on parameters passed to findObjects method.
 */
class FindObjectsQueryFactory extends AbstractQueryFactory implements PartialQueryFactory
{
    private $additionalTablesFetch;
    private $filterString;
    /**
     * @var Cache
     */
    private $cache;

    public function __construct(string $mainTable, array $additionalTablesFetch, $filterString, $orderBy, TDBMService $tdbmService, Schema $schema, OrderByAnalyzer $orderByAnalyzer, Cache $cache)
    {
        parent::__construct($tdbmService, $schema, $orderByAnalyzer, $mainTable, $orderBy);
        $this->additionalTablesFetch = $additionalTablesFetch;
        $this->filterString = $filterString;
        $this->cache = $cache;
    }

    protected function compute(): void
    {
        $key = 'FindObjectsQueryFactory_'.$this->mainTable.'__'.implode('_/_', $this->additionalTablesFetch).'__'.$this->filterString.'__'.$this->orderBy;
        if ($this->cache->contains($key)) {
            [
                $this->magicSql,
                $this->magicSqlCount,
                $this->columnDescList,
                $this->magicSqlSubQuery
            ] = $this->cache->fetch($key);
            return;
        }

        list($columnDescList, $columnsList, $orderString) = $this->getColumnsList($this->mainTable, $this->additionalTablesFetch, $this->orderBy, true);

        $sql = 'SELECT DISTINCT '.implode(', ', $columnsList).' FROM MAGICJOIN('.$this->mainTable.')';

        $pkColumnNames = $this->tdbmService->getPrimaryKeyColumns($this->mainTable);
        $mysqlPlatform = new MySqlPlatform();
        $pkColumnNames = array_map(function ($pkColumn) use ($mysqlPlatform) {
            return $mysqlPlatform->quoteIdentifier($this->mainTable).'.'.$mysqlPlatform->quoteIdentifier($pkColumn);
        }, $pkColumnNames);

        $subQuery = 'SELECT DISTINCT '.implode(', ', $pkColumnNames).' FROM MAGICJOIN('.$this->mainTable.')';

        if (count($pkColumnNames) === 1 || $this->tdbmService->getConnection()->getDatabasePlatform() instanceof MySqlPlatform) {
            $countSql = 'SELECT COUNT(DISTINCT '.implode(', ', $pkColumnNames).') FROM MAGICJOIN('.$this->mainTable.')';
        } else {
            $countSql = 'SELECT COUNT(*) FROM ('.$subQuery.') tmp';
        }

        if (!empty($this->filterString)) {
            $sql .= ' WHERE '.$this->filterString;
            $countSql .= ' WHERE '.$this->filterString;
            $subQuery .= ' WHERE '.$this->filterString;
        }

        if (!empty($orderString)) {
            $sql .= ' ORDER BY '.$orderString;
        }

        $this->magicSql = $sql;
        $this->magicSqlCount = $countSql;
        $this->magicSqlSubQuery = $subQuery;
        $this->columnDescList = $columnDescList;

        $this->cache->save($key, [
            $this->magicSql,
            $this->magicSqlCount,
            $this->columnDescList,
            $this->magicSqlSubQuery,
        ]);
    }

    /**
     * Generates a SQL query to be used as a sub-query.
     * @param array<string, mixed> $parameters
     */
    public function getPartialQuery(StorageNode $storageNode, MagicQuery $magicQuery, array $parameters): PartialQuery
    {
        $mysqlPlatform = new MySqlPlatform();

        // Special case: if the main table is part of an inheritance relationship, we need to get all related tables
        $relatedTables = $this->tdbmService->_getRelatedTablesByInheritance($this->mainTable);
        if (count($relatedTables) === 1) {
            $sql = 'FROM '.$mysqlPlatform->quoteIdentifier($this->mainTable);
        } else {
            // Let's use MagicQuery to build the query
            $sql = 'FROM MAGICJOIN('.$this->mainTable.')';
        }

        if (!empty($this->filterString)) {
            $sql .= ' WHERE '.$this->filterString;
        }

        return new StaticPartialQuery($sql, $parameters, $relatedTables, $storageNode, $magicQuery);
    }
}
