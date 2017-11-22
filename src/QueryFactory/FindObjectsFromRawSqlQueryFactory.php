<?php

namespace TheCodingMachine\TDBM\QueryFactory;

use Doctrine\DBAL\Schema\Schema;
use TheCodingMachine\TDBM\TDBMException;
use TheCodingMachine\TDBM\TDBMService;
use PHPSQLParser\PHPSQLCreator;
use PHPSQLParser\PHPSQLParser;

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

    public function sort($orderBy)
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

    private function compute(string $sql, ?string $sqlCount)
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

    private function processParsedUnionQuery(array $parsedSql, ?string $sqlCount): array
    {
        $selects = $parsedSql['UNION'];

        $parsedSqlList = [];
        $columnDescriptors = [];

        foreach ($selects as $select) {
            [$selectProcessedSql, $selectProcessedCountSql, $columnDescriptors] = $this->processParsedSelectQuery($select, '');

            // Let's reparse the returned SQL (not the most efficient way of doing things)
            $parser = new PHPSQLParser();
            $parsedSql = $parser->parse($selectProcessedSql);

            $parsedSqlList[] = $parsedSql;
        }

        // Let's rebuild the UNION query
        $query = ['UNION' => $parsedSqlList];

        // The count is the SUM of the count of the UNIONs
        $countQuery = $this->generateWrappedSqlCount($query);

        $generator = new PHPSQLCreator();

        $processedSql = $generator->create($query);
        $processedSqlCount = $generator->create($countQuery);

        return [$processedSql, $sqlCount ?? $processedSqlCount, $columnDescriptors];
    }

    /**
     * @param array $parsedSql
     * @param null|string $sqlCount
     * @return mixed[]
     */
    private function processParsedSelectQuery(array $parsedSql, ?string $sqlCount): array
    {
        // 1: let's reformat the SELECT and construct our columns
        list($select, $columnDescriptors) = $this->formatSelect($parsedSql['SELECT']);
        $generator = new PHPSQLCreator();
        $parsedSql['SELECT'] = $select;
        $processedSql = $generator->create($parsedSql);

        // 2: let's compute the count query if needed
        if ($sqlCount === null) {
            $parsedSqlCount = $this->generateParsedSqlCount($parsedSql);
            $processedSqlCount = $generator->create($parsedSqlCount);
        } else {
            $processedSqlCount = $sqlCount;
        }

        return [$processedSql, $processedSqlCount, $columnDescriptors];
    }

    private function formatSelect($baseSelect)
    {
        $relatedTables = $this->tdbmService->_getRelatedTablesByInheritance($this->mainTable);
        $tableGroup = $this->getTableGroupName($relatedTables);

        $connection = $this->tdbmService->getConnection();
        $formattedSelect = [];
        $columnDescritors = [];
        $fetchedTables = [];

        foreach ($baseSelect as $entry) {
            if ($entry['expr_type'] !== 'colref') {
                $formattedSelect[] = $entry;
                continue;
            }

            $noQuotes = $entry['no_quotes'];
            if ($noQuotes['delim'] !== '.' || count($noQuotes['parts']) !== 2) {
                $formattedSelect[] = $entry;
                continue;
            }

            $tableName = $noQuotes['parts'][0];
            if (!in_array($tableName, $relatedTables)) {
                $formattedSelect[] = $entry;
                continue;
            }

            $columnName = $noQuotes['parts'][1];
            if ($columnName !== '*') {
                $formattedSelect[] = $entry;
                continue;
            }

            $table = $this->schema->getTable($tableName);
            foreach ($table->getColumns() as $column) {
                $columnName = $column->getName();
                $alias = "{$tableName}____{$columnName}";
                $formattedSelect[] = [
                    'expr_type' => 'colref',
                    'base_expr' => $connection->quoteIdentifier($tableName).'.'.$connection->quoteIdentifier($columnName),
                    'no_quotes' => [
                        'delim' => '.',
                        'parts' => [
                            $tableName,
                            $columnName
                        ]
                    ],
                    'alias' => [
                        'as' => true,
                        'name' => $alias,
                    ]
                ];

                $columnDescritors[$alias] = [
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
            $formattedSelect[$i]['delim'] = ',';
        }
        return [$formattedSelect, $columnDescritors];
    }

    private function generateParsedSqlCount($parsedSql)
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

    private function generateSimpleSqlCount($parsedSql)
    {
        $parsedSql['SELECT'] =                 [[
            'expr_type' => 'aggregate_function',
            'alias' => [
                'as' => true,
                'name' => 'cnt',
            ],
            'base_expr' => 'COUNT',
            'sub_tree' => $parsedSql['SELECT'],
            'delim' => false,
        ]];

        return $parsedSql;
    }

    private function generateGroupedSqlCount($parsedSql)
    {
        $group = $parsedSql['GROUP'];
        unset($parsedSql['GROUP']);
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
        return $parsedSql;
    }

    private function generateWrappedSqlCount($parsedSql)
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

    protected function getTableGroupName(array $relatedTables)
    {
        sort($relatedTables);
        return implode('_``_', $relatedTables);
    }
}
