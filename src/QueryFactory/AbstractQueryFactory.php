<?php

namespace TheCodingMachine\TDBM\QueryFactory;

use Doctrine\DBAL\Schema\Schema;
use TheCodingMachine\TDBM\OrderByAnalyzer;
use TheCodingMachine\TDBM\TDBMInvalidArgumentException;
use TheCodingMachine\TDBM\TDBMService;
use TheCodingMachine\TDBM\UncheckedOrderBy;

abstract class AbstractQueryFactory implements QueryFactory
{
    /**
     * @var TDBMService
     */
    protected $tdbmService;

    /**
     * @var Schema
     */
    protected $schema;

    /**
     * @var OrderByAnalyzer
     */
    protected $orderByAnalyzer;

    /**
     * @var string|UncheckedOrderBy|null
     */
    protected $orderBy;

    protected $magicSql;
    protected $magicSqlCount;
    protected $columnDescList;

    /**
     * @param TDBMService $tdbmService
     */
    public function __construct(TDBMService $tdbmService, Schema $schema, OrderByAnalyzer $orderByAnalyzer, $orderBy)
    {
        $this->tdbmService = $tdbmService;
        $this->schema = $schema;
        $this->orderByAnalyzer = $orderByAnalyzer;
        $this->orderBy = $orderBy;
    }

    /**
     * Returns the column list that must be fetched for the SQL request.
     *
     * Note: MySQL dictates that ORDER BYed columns should appear in the SELECT clause.
     *
     * @param string                       $mainTable
     * @param array                        $additionalTablesFetch
     * @param string|UncheckedOrderBy|null $orderBy
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function getColumnsList(string $mainTable, array $additionalTablesFetch = array(), $orderBy = null)
    {
        // From the table name and the additional tables we want to fetch, let's build a list of all tables
        // that must be part of the select columns.

        $connection = $this->tdbmService->getConnection();

        $tableGroups = [];
        $allFetchedTables = $this->tdbmService->_getRelatedTablesByInheritance($mainTable);
        $tableGroupName = $this->getTableGroupName($allFetchedTables);
        foreach ($allFetchedTables as $table) {
            $tableGroups[$table] = $tableGroupName;
        }

        $columnsList = [];
        $columnDescList = [];
        $sortColumn = 0;
        $reconstructedOrderBy = null;

        if (is_string($orderBy)) {
            $orderBy = trim($orderBy);
            if ($orderBy === '') {
                $orderBy = null;
            }
        }

        // Now, let's deal with "order by columns"
        if ($orderBy !== null) {
            if ($orderBy instanceof UncheckedOrderBy) {
                $securedOrderBy = false;
                $orderBy = $orderBy->getOrderBy();
                $reconstructedOrderBy = $orderBy;
            } else {
                $securedOrderBy = true;
                $reconstructedOrderBys = [];
            }
            $orderByColumns = $this->orderByAnalyzer->analyzeOrderBy($orderBy);

            // If we sort by a column, there is a high chance we will fetch the bean containing this column.
            // Hence, we should add the table to the $additionalTablesFetch
            foreach ($orderByColumns as $orderByColumn) {
                if ($orderByColumn['type'] === 'colref') {
                    if ($orderByColumn['table'] !== null) {
                        $additionalTablesFetch[] = $orderByColumn['table'];
                    }
                    if ($securedOrderBy) {
                        $reconstructedOrderBys[] = ($orderByColumn['table'] !== null ? $connection->quoteIdentifier($orderByColumn['table']).'.' : '').$connection->quoteIdentifier($orderByColumn['column']).' '.$orderByColumn['direction'];
                    }
                } elseif ($orderByColumn['type'] === 'expr') {
                    $sortColumnName = 'sort_column_'.$sortColumn;
                    $columnsList[] = $orderByColumn['expr'].' as '.$sortColumnName;
                    $columnDescList[$sortColumnName] = [
                        'tableGroup' => null,
                    ];
                    ++$sortColumn;

                    if ($securedOrderBy) {
                        throw new TDBMInvalidArgumentException('Invalid ORDER BY column: "'.$orderByColumn['expr'].'". If you want to use expression in your ORDER BY clause, you must wrap them in a UncheckedOrderBy object. For instance: new UncheckedOrderBy("col1 + col2 DESC")');
                    }
                }
            }

            if ($reconstructedOrderBy === null) {
                $reconstructedOrderBy = implode(', ', $reconstructedOrderBys);
            }
        }

        foreach ($additionalTablesFetch as $additionalTable) {
            $relatedTables = $this->tdbmService->_getRelatedTablesByInheritance($additionalTable);
            $tableGroupName = $this->getTableGroupName($relatedTables);
            foreach ($relatedTables as $table) {
                $tableGroups[$table] = $tableGroupName;
            }
            $allFetchedTables = array_merge($allFetchedTables, $relatedTables);
        }

        // Let's remove any duplicate
        $allFetchedTables = array_flip(array_flip($allFetchedTables));
        
        // We quote in MySQL because MagicJoin requires MySQL style quotes
        $mysqlPlatform = new MySqlPlatform();

        // Now, let's build the column list
        foreach ($allFetchedTables as $table) {
            foreach ($this->schema->getTable($table)->getColumns() as $column) {
                $columnName = $column->getName();
                $columnDescList[$table.'____'.$columnName] = [
                    'as' => $table.'____'.$columnName,
                    'table' => $table,
                    'column' => $columnName,
                    'type' => $column->getType(),
                    'tableGroup' => $tableGroups[$table],
                ];
                $columnsList[] = $mysqlPlatform->quoteIdentifier($table).'.'.$mysqlPlatform->quoteIdentifier($columnName).' as '.
                    $connection->quoteIdentifier($table.'____'.$columnName);
            }
        }

        return [$columnDescList, $columnsList, $reconstructedOrderBy];
    }

    abstract protected function compute();

    /**
     * Returns an identifier for the group of tables passed in parameter.
     *
     * @param string[] $relatedTables
     *
     * @return string
     */
    protected function getTableGroupName(array $relatedTables)
    {
        sort($relatedTables);

        return implode('_``_', $relatedTables);
    }

    public function getMagicSql() : string
    {
        if ($this->magicSql === null) {
            $this->compute();
        }

        return $this->magicSql;
    }

    public function getMagicSqlCount() : string
    {
        if ($this->magicSqlCount === null) {
            $this->compute();
        }

        return $this->magicSqlCount;
    }

    public function getColumnDescriptors() : array
    {
        if ($this->columnDescList === null) {
            $this->compute();
        }

        return $this->columnDescList;
    }

    /**
     * Sets the ORDER BY directive executed in SQL.
     *
     * For instance:
     *
     *  $queryFactory->sort('label ASC, status DESC');
     *
     * **Important:** TDBM does its best to protect you from SQL injection. In particular, it will only allow column names in the "ORDER BY" clause. This means you are safe to pass input from the user directly in the ORDER BY parameter.
     * If you want to pass an expression to the ORDER BY clause, you will need to tell TDBM to stop checking for SQL injections. You do this by passing a `UncheckedOrderBy` object as a parameter:
     *
     *  $queryFactory->sort(new UncheckedOrderBy('RAND()'))
     *
     * @param string|UncheckedOrderBy|null $orderBy
     */
    public function sort($orderBy)
    {
        $this->orderBy = $orderBy;
        $this->magicSql = null;
        $this->magicSqlCount = null;
        $this->columnDescList = null;
    }
}
