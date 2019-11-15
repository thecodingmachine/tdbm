<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\QueryFactory;

use PHPSQLParser\utils\ExpressionType;
use TheCodingMachine\TDBM\ResultIterator;
use function array_unique;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Schema;
use function in_array;
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

    /**
     * @var string|null
     */
    protected $magicSql;
    /**
     * @var string|null
     */
    protected $magicSqlCount;
    /**
     * @var string|null
     */
    protected $magicSqlSubQuery;
    protected $columnDescList;
    protected $subQueryColumnDescList;
    /**
     * @var string
     */
    protected $mainTable;
    /** @var null|ResultIterator */
    protected $resultIterator;

    /**
     * @param TDBMService $tdbmService
     * @param Schema $schema
     * @param OrderByAnalyzer $orderByAnalyzer
     * @param string|UncheckedOrderBy|null $orderBy
     */
    public function __construct(TDBMService $tdbmService, Schema $schema, OrderByAnalyzer $orderByAnalyzer, string $mainTable, $orderBy)
    {
        $this->tdbmService = $tdbmService;
        $this->schema = $schema;
        $this->orderByAnalyzer = $orderByAnalyzer;
        $this->orderBy = $orderBy;
        $this->mainTable = $mainTable;
    }

    public function setResultIterator(ResultIterator $resultIterator): void
    {
        $this->resultIterator = $resultIterator;
    }

    /**
     * Returns the column list that must be fetched for the SQL request.
     *
     * Note: MySQL dictates that ORDER BYed columns should appear in the SELECT clause.
     *
     * @param string $mainTable
     * @param string[] $additionalTablesFetch
     * @param string|UncheckedOrderBy|null $orderBy
     *
     * @param bool $canAddAdditionalTablesFetch Set to true if the function can add additional tables to fetch (so if the factory generates its own FROM clause)
     * @return mixed[] A 3 elements array: [$columnDescList, $columnsList, $reconstructedOrderBy]
     */
    protected function getColumnsList(string $mainTable, array $additionalTablesFetch = array(), $orderBy = null, bool $canAddAdditionalTablesFetch = false): array
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
            $securedOrderBy = true;
            $reconstructedOrderBys = [];
            if ($orderBy instanceof UncheckedOrderBy) {
                $securedOrderBy = false;
                $orderBy = $orderBy->getOrderBy();
                $reconstructedOrderBy = $orderBy;
            }
            $orderByColumns = $this->orderByAnalyzer->analyzeOrderBy($orderBy);

            // If we sort by a column, there is a high chance we will fetch the bean containing this column.
            // Hence, we should add the table to the $additionalTablesFetch
            foreach ($orderByColumns as $orderByColumn) {
                if ($orderByColumn['type'] === ExpressionType::COLREF) {
                    if ($orderByColumn['table'] !== null) {
                        if ($canAddAdditionalTablesFetch) {
                            $additionalTablesFetch[] = $orderByColumn['table'];
                        } else {
                            $sortColumnName = 'sort_column_'.$sortColumn;
                            $mysqlPlatform = new MySqlPlatform();
                            $columnsList[] = $mysqlPlatform->quoteIdentifier($orderByColumn['table']).'.'.$mysqlPlatform->quoteIdentifier($orderByColumn['column']).' as '.$sortColumnName;
                            $columnDescList[$sortColumnName] = [
                                'tableGroup' => null,
                            ];
                            ++$sortColumn;
                        }
                    }
                    if ($securedOrderBy) {
                        // Let's protect via MySQL since we go through MagicJoin
                        $mysqlPlatform = new MySqlPlatform();
                        $reconstructedOrderBys[] = ($orderByColumn['table'] !== null ? $mysqlPlatform->quoteIdentifier($orderByColumn['table']).'.' : '').$mysqlPlatform->quoteIdentifier($orderByColumn['column']).' '.$orderByColumn['direction'];
                    }
                } elseif ($orderByColumn['type'] === 'expr') {
                    if ($securedOrderBy) {
                        throw new TDBMInvalidArgumentException('Invalid ORDER BY column: "'.$orderByColumn['expr'].'". If you want to use expression in your ORDER BY clause, you must wrap them in a UncheckedOrderBy object. For instance: new UncheckedOrderBy("col1 + col2 DESC")');
                    }

                    $sortColumnName = 'sort_column_'.$sortColumn;
                    $columnsList[] = $orderByColumn['expr'].' as '.$sortColumnName;
                    $columnDescList[$sortColumnName] = [
                        'tableGroup' => null,
                    ];
                    ++$sortColumn;
                }
            }

            if ($reconstructedOrderBy === null) {
                $reconstructedOrderBy = implode(', ', $reconstructedOrderBys);
            }
        }

        foreach ($additionalTablesFetch as $additionalTable) {
            if (in_array($additionalTable, $allFetchedTables, true)) {
                continue;
            }

            $relatedTables = $this->tdbmService->_getRelatedTablesByInheritance($additionalTable);
            $tableGroupName = $this->getTableGroupName($relatedTables);
            foreach ($relatedTables as $table) {
                $tableGroups[$table] = $tableGroupName;
            }
            $allFetchedTables = array_merge($allFetchedTables, $relatedTables);
        }

        // Let's remove any duplicate
        $allFetchedTables = array_unique($allFetchedTables);
        
        // We quote in MySQL because MagicJoin requires MySQL style quotes
        $mysqlPlatform = new MySqlPlatform();

        // Now, let's build the column list
        foreach ($allFetchedTables as $table) {
            foreach ($this->schema->getTable($table)->getColumns() as $column) {
                $columnName = $column->getName();
                if ($this->resultIterator === null // @TODO (gua) don't take care of whitelist in case of LIMIT below 2
                    || $table !== $mainTable
                    || $this->resultIterator->isInWhitelist($columnName, $table)
                ) {
                    $columnDescList[$table . '____' . $columnName] = [
                        'as' => $table . '____' . $columnName,
                        'table' => $table,
                        'column' => $columnName,
                        'type' => $column->getType(),
                        'tableGroup' => $tableGroups[$table],
                    ];
                    $columnsList[] = $mysqlPlatform->quoteIdentifier($table) . '.' . $mysqlPlatform->quoteIdentifier($columnName) . ' as ' .
                        $connection->quoteIdentifier($table . '____' . $columnName);
                }
            }
        }

        return [$columnDescList, $columnsList, $reconstructedOrderBy];
    }

    abstract protected function compute(): void;

    /**
     * Returns an identifier for the group of tables passed in parameter.
     *
     * @param string[] $relatedTables
     *
     * @return string
     */
    protected function getTableGroupName(array $relatedTables): string
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

    public function getMagicSqlSubQuery() : string
    {
        if ($this->magicSqlSubQuery === null) {
            $this->compute();
        }

        return $this->magicSqlSubQuery;
    }

    public function getColumnDescriptors() : array
    {
        if ($this->columnDescList === null) {
            $this->compute();
        }

        return $this->columnDescList;
    }

    /**
     * @return string[][] An array of column descriptors. Value is an array with those keys: table, column
     */
    public function getSubQueryColumnDescriptors() : array
    {
        if ($this->subQueryColumnDescList === null) {
            $columns = $this->tdbmService->getPrimaryKeyColumns($this->mainTable);
            $descriptors = [];
            foreach ($columns as $column) {
                $descriptors[] = [
                    'table' => $this->mainTable,
                    'column' => $column
                ];
            }
            $this->subQueryColumnDescList = $descriptors;
        }

        return $this->subQueryColumnDescList;
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
    public function sort($orderBy): void
    {
        $this->orderBy = $orderBy;
        $this->magicSql = null;
        $this->magicSqlCount = null;
        $this->columnDescList = null;
    }
}
