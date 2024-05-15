<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\QueryFactory;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Schema;
use PHPSQLParser\builders\OrderByBuilder;
use PHPSQLParser\builders\SelectStatementBuilder;
use TheCodingMachine\TDBM\TDBMException;
use TheCodingMachine\TDBM\TDBMService;
use PHPSQLParser\PHPSQLCreator;
use PHPSQLParser\PHPSQLParser;

use function array_merge;

/**
 * This class is in charge of formatting the SQL passed to findObjectsFromRawSql method.
 */
class FindObjectsFromRawSqlQueryFactory implements QueryFactory
{
    /**
     * @var array[]
     */
    protected $columnDescriptors;
    /**
     * @var Schema
     */
    private $schema;
    /**
     * @var string
     */
    private $processedSql;
    /**
     * @var string
     */
    private $processedSqlCount;
    /**
     * @var TDBMService
     */
    private $tdbmService;
    /**
     * @var string
     */
    private $mainTable;

    /**
     * FindObjectsFromRawSqlQueryFactory constructor.
     * @param TDBMService $tdbmService
     * @param Schema $schema
     * @param string $mainTable
     * @param string $sql
     * @param string $sqlCount
     */
    public function __construct(TDBMService $tdbmService, Schema $schema, string $mainTable, string $sql, string $sqlCount = null)
    {
        $this->tdbmService = $tdbmService;
        $this->schema = $schema;
        $this->mainTable = $mainTable;

        [$this->processedSql, $this->processedSqlCount, $this->columnDescriptors] = $this->compute($sql, $sqlCount);
    }

    public function sort($orderBy): void
    {
        throw new TDBMException('sort not supported for raw sql queries');
    }

    public function getMagicSql(): string
    {
        return $this->processedSql;
    }

    public function getMagicSqlCount(): string
    {
        return $this->processedSqlCount;
    }

    public function getColumnDescriptors(): array
    {
        return $this->columnDescriptors;
    }

    /**
     * @param string $sql
     * @param null|string $sqlCount
     * @return mixed[] An array of 3 elements: [$processedSql, $processedSqlCount, $columnDescriptors]
     * @throws TDBMException
     */
    private function compute(string $sql, ?string $sqlCount): array
    {
        $parser = new PHPSQLParser();
        $parsedSql = $parser->parse($sql);

        if (isset($parsedSql['SELECT'])) {
            [$processedSql, $processedSqlCount, $columnDescriptors] = $this->processParsedSelectQuery($parsedSql, $sqlCount);
        } elseif (isset($parsedSql['UNION'])) {
            [$processedSql, $processedSqlCount, $columnDescriptors] = $this->processParsedUnionQuery($parsedSql, $sqlCount);
        } else {
            throw new TDBMException('Unable to analyze query "'.$sql.'"');
        }

        return [$processedSql, $processedSqlCount, $columnDescriptors];
    }

    /**
     * @param mixed[] $parsedSql
     * @param null|string $sqlCount
     * @return mixed[] An array of 3 elements: [$processedSql, $processedSqlCount, $columnDescriptors]
     * @throws \PHPSQLParser\exceptions\UnsupportedFeatureException|\PHPSQLParser\exceptions\UnableToCreateSQLException
     */
    private function processParsedUnionQuery(array $parsedSql, ?string $sqlCount): array
    {
        $selects = $parsedSql['UNION'];

        $parsedSqlList = [];
        $columnDescriptors = [];

        foreach ($selects as $select) {
            [$selectProcessedSql, $selectProcessedCountSql, $columnDescriptors] = $this->processParsedSelectQuery($select, '');

            // Let's reparse the returned SQL (not the most efficient way of doing things)
            $parser = new PHPSQLParser();
            $parsedSelectSql = $parser->parse($selectProcessedSql);

            $parsedSqlList[] = $parsedSelectSql;
        }

        // Let's rebuild the UNION query
        $query = ['UNION' => $parsedSqlList];

        // The count is the SUM of the count of the UNIONs
        $countQuery = $this->generateWrappedSqlCount($query);

        $generator = new PHPSQLCreator();

        // Replaced the default generator by our own to add parenthesis around each SELECT
        $processedSql = $this->buildUnion($query);
        $processedSqlCount = $generator->create($countQuery);

        // Let's add the ORDER BY if any
        if (isset($parsedSql['0']['ORDER'])) {
            $orderByBuilder = new OrderByBuilder();
            $processedSql .= " " . $orderByBuilder->build($parsedSql['0']['ORDER']);
        }

        return [$processedSql, $sqlCount ?? $processedSqlCount, $columnDescriptors];
    }

    /**
     * @param mixed[] $parsed
     */
    private function buildUnion(array $parsed): string
    {
        $selectBuilder = new SelectStatementBuilder();

        return implode(' UNION ', array_map(function ($clause) use ($selectBuilder) {
            return '(' . $selectBuilder->build($clause) . ')';
        }, $parsed['UNION']));
    }

    /**
     * @param mixed[] $parsedSql
     * @param null|string $sqlCount
     * @return mixed[] An array of 3 elements: [$processedSql, $processedSqlCount, $columnDescriptors]
     */
    private function processParsedSelectQuery(array $parsedSql, ?string $sqlCount): array
    {
        // 1: let's reformat the SELECT and construct our columns
        list($select, $countSelect, $columnDescriptors) = $this->formatSelect($parsedSql['SELECT']);
        $generator = new PHPSQLCreator();
        $parsedSql['SELECT'] = $select;
        $processedSql = $generator->create($parsedSql);

        // 2: let's compute the count query if needed
        if ($sqlCount === null) {
            $parsedCountSql = $parsedSql;
            $parsedCountSql['SELECT'] = $countSelect;
            $parsedSqlCount = $this->generateParsedSqlCount($parsedCountSql);
            $processedSqlCount = $generator->create($parsedSqlCount);
        } else {
            $processedSqlCount = $sqlCount;
        }

        return [$processedSql, $processedSqlCount, $columnDescriptors];
    }

    /**
     * @param mixed[] $baseSelect
     * @return mixed[] An array of 2 elements: [$formattedSelect, $columnDescriptors]
     * @throws TDBMException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    private function formatSelect(array $baseSelect): array
    {
        $relatedTables = $this->tdbmService->_getRelatedTablesByInheritance($this->mainTable);
        $tableGroup = $this->getTableGroupName($relatedTables);

        $connection = $this->tdbmService->getConnection();
        $formattedSelect = [];
        $formattedCountSelect = [];
        $columnDescriptors = [];
        $fetchedTables = [];

        foreach ($baseSelect as $entry) {
            if ($entry['expr_type'] !== 'colref') {
                $formattedSelect[] = $entry;
                $formattedCountSelect[] = $entry;
                continue;
            }

            $noQuotes = $entry['no_quotes'];
            if ($noQuotes['delim'] !== '.' || count($noQuotes['parts']) !== 2) {
                $formattedSelect[] = $entry;
                $formattedCountSelect[] = $entry;
                continue;
            }

            $tableName = $noQuotes['parts'][0];
            if (!in_array($tableName, $relatedTables)) {
                $formattedSelect[] = $entry;
                $formattedCountSelect[] = $entry;
                continue;
            }

            $columnName = $noQuotes['parts'][1];
            if ($columnName !== '*') {
                $formattedSelect[] = $entry;
                $formattedCountSelect[] = $entry;
                continue;
            }

            $table = $this->schema->getTable($tableName);
            $pkColumns = $table->getPrimaryKeyColumns();
            foreach ($table->getColumns() as $column) {
                $columnName = $column->getName();
                $alias = AbstractQueryFactory::getColumnAlias($tableName, $columnName);
                $astColumn = [
                    'expr_type' => 'colref',
                    'base_expr' => $connection->quoteIdentifier($tableName) . '.' . $connection->quoteIdentifier($columnName),
                    'no_quotes' => [
                        'delim' => '.',
                        'parts' => [
                            $tableName,
                            $columnName
                        ]
                    ],
                    'alias' => [
                        'as' => true,
                        'name' => $connection->quoteIdentifier($alias),
                    ]
                ];
                $formattedSelect[] = $astColumn;
                if (in_array($columnName, $pkColumns)) {
                    $formattedCountSelect[] = $astColumn;
                }
                $columnDescriptors[$alias] = [
                    'as' => $alias,
                    'table' => $tableName,
                    'column' => $columnName,
                    'type' => $column->getType(),
                    'tableGroup' => $tableGroup,
                ];
            }
            $fetchedTables[] = $tableName;
        }

        $missingTables = array_diff($relatedTables, $fetchedTables);
        if (!empty($missingTables)) {
            throw new TDBMException('Missing tables '.implode(', ', $missingTables).' in SELECT statement');
        }

        for ($i = 0; $i < count($formattedSelect) - 1; $i++) {
            if (!isset($formattedSelect[$i]['delim'])) {
                $formattedSelect[$i]['delim'] = ',';
            }
        }

        for ($i = 0; $i < count($formattedCountSelect) - 1; $i++) {
            if (!isset($formattedCountSelect[$i]['delim'])) {
                $formattedCountSelect[$i]['delim'] = ',';
            }
        }
        return [$formattedSelect, $formattedCountSelect, $columnDescriptors];
    }

    /**
     * @param mixed[] $parsedSql
     * @return mixed[]
     */
    private function generateParsedSqlCount(array $parsedSql): array
    {
        if (isset($parsedSql['ORDER'])) {
            unset($parsedSql['ORDER']);
        }

        if (!isset($parsedSql['GROUP'])) {
            // most simple case:no GROUP BY in query
            return $this->generateSimpleSqlCount($parsedSql);
        } elseif (!isset($parsedSql['HAVING'])) {
            // GROUP BY without HAVING statement: let's COUNT the DISTINCT grouped columns
            return $this->generateGroupedSqlCount($parsedSql);
        } else {
            // GROUP BY with a HAVING statement: we'll have to wrap the query
            return $this->generateWrappedSqlCount($parsedSql);
        }
    }

    /**
     * @param mixed[] $parsedSql The AST of the SQL query
     * @return mixed[] An AST representing the matching COUNT query
     */
    private function generateSimpleSqlCount(array $parsedSql): array
    {
        // If the query is a DISTINCT, we need to deal with the count.


        // We need to count on the same columns: COUNT(DISTINCT country.id, country.label) ....
        // but we need to remove the "alias" bit.

        if ($this->isDistinctQuery($parsedSql)) {
            // Only MySQL can do DISTINCT counts.
            // Other databases should wrap the query
            if (!$this->tdbmService->getConnection()->getSchemaManager()->getDatabasePlatform() instanceof MySqlPlatform) {
                return $this->generateWrappedSqlCount($parsedSql);
            }

            $countSubExpr = array_map(function (array $item) {
                unset($item['alias']);
                return $item;
            }, $parsedSql['SELECT']);
        } else {
            $countSubExpr = [
                [
                'expr_type' => 'colref',
                'base_expr' => '*',
                'sub_tree' => false
                ]
            ];
        }

        $parsedSql['SELECT'] = [[
            'expr_type' => 'aggregate_function',
            'alias' => [
                'as' => true,
                'name' => 'cnt',
            ],
            'base_expr' => 'COUNT',
            'sub_tree' => $countSubExpr,
            'delim' => false,
        ]];

        return $parsedSql;
    }

    /**
     * @param mixed[] $parsedSql AST to analyze
     * @return bool
     */
    private function isDistinctQuery(array $parsedSql): bool
    {
        foreach ($parsedSql['SELECT'] as $item) {
            if ($item['expr_type'] === 'reserved' && $item['base_expr'] === 'DISTINCT') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param mixed[] $parsedSql The AST of the SQL query
     * @return mixed[] An AST representing the matching COUNT query
     */
    private function generateGroupedSqlCount(array $parsedSql): array
    {
        $group = $parsedSql['GROUP'];
        unset($parsedSql['GROUP']);

        // Count(DISTINCT ...) on multiple columns is only valid in MySQL (unsupported on Pgsql or Oracle). For those, we need to do a subquery.
        if (count($group) === 1 || $this->tdbmService->getConnection()->getDatabasePlatform() instanceof MySqlPlatform) {
            $parsedSql['SELECT'] = [[
                'expr_type' => 'aggregate_function',
                'alias' => [
                    'as' => true,
                    'name' => 'cnt',
                ],
                'base_expr' => 'COUNT',
                'sub_tree' => array_merge([[
                    'expr_type' => 'reserved',
                    'base_expr' => 'DISTINCT',
                    'delim' => ','
                ]], $group),
                'delim' => false,
            ]];
        } else {
            $innerColumns = [[
                'expr_type' => 'reserved',
                'base_expr' => 'DISTINCT',
                'delim' => ' '
            ]];
            foreach ($group as $item) {
                $item['delim'] = ',';
                $innerColumns[] = $item;
            }
            $innerColumns[count($innerColumns) - 1]['delim'] = false;
            $parsedSql['SELECT'] = $innerColumns;

            $parsedSql = [
                'SELECT' =>
                    [
                        0 =>
                            [
                                'expr_type' => 'aggregate_function',
                                'alias' =>
                                    [
                                        'as' => true,
                                        'name' => 'cnt',
                                        'base_expr' => 'AS cnt',
                                        'no_quotes' =>
                                            [
                                                'delim' => false,
                                                'parts' =>
                                                    [
                                                        0 => 'cnt',
                                                    ],
                                            ],
                                    ],
                                'base_expr' => 'COUNT',
                                'sub_tree' =>
                                    [
                                        0 =>
                                            [
                                                'expr_type' => 'colref',
                                                'base_expr' => '*',
                                                'sub_tree' => false,
                                            ],
                                    ],
                                'delim' => false,
                            ],
                    ],
                'FROM' =>
                    [
                        0 =>
                            [
                                'expr_type' => 'subquery',
                                'alias' =>
                                    [
                                        'as' => false,
                                        'name' => 'subquery',
                                        'no_quotes' =>
                                            [
                                                'delim' => false,
                                                'parts' =>
                                                    [
                                                        0 => 'subquery',
                                                    ],
                                            ],
                                        'base_expr' => 'subquery',
                                    ],
                                'hints' => false,
                                'join_type' => 'JOIN',
                                'ref_type' => false,
                                'ref_clause' => false,
                                //'base_expr' => 'SELECT id FROM country',
                                'sub_tree' => $parsedSql
                            ],
                    ],
                ];
        }
        return $parsedSql;
    }

    /**
     * @param mixed[] $parsedSql The AST of the SQL query
     * @return mixed[] An AST representing the matching COUNT query
     */
    private function generateWrappedSqlCount(array $parsedSql): array
    {
        return [
            'SELECT' => [[
                'expr_type' => 'aggregate_function',
                'alias' => [
                    'as' => true,
                    'name' => 'cnt',
                ],
                'base_expr' => 'COUNT',
                'sub_tree' => [
                    [
                        'expr_type' => 'colref',
                        'base_expr' => '*',
                        'sub_tree' => false
                    ]
                ],
                'delim' => false,
            ]],
            'FROM' => [[
                'expr_type' => 'subquery',
                'alias' => [
                    'as' => true,
                    'name' => '____query'
                ],
                'sub_tree' => $parsedSql,
            ]]
        ];
    }

    /**
     * @param string[] $relatedTables
     * @return string
     */
    protected function getTableGroupName(array $relatedTables): string
    {
        sort($relatedTables);
        return implode('_``_', $relatedTables);
    }

    /**
     * Returns a sub-query to be used in another query.
     * A sub-query is similar to a query except it returns only the primary keys of the table (to be used as filters)
     *
     * @return string
     */
    public function getMagicSqlSubQuery(): string
    {
        throw new TDBMException('Using resultset generated from findFromRawSql as subqueries is unsupported for now.');
    }

    /**
     * @return array<int, array{table: string, column: string}> An array of column descriptors.
     */
    public function getSubQueryColumnDescriptors(): array
    {
        throw new TDBMException('Using resultset generated from findFromRawSql as subqueries is unsupported for now.');
    }
}
