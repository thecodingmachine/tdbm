<?php

namespace TheCodingMachine\TDBM\QueryFactory;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use TheCodingMachine\TDBM\TDBMException;
use TheCodingMachine\TDBM\TDBMService;
use TheCodingMachine\TDBM\UncheckedOrderBy;
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
    private $sql;
    /**
     * @var string
     */
    private $sqlCount;
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
        $this->sql = $sql;
        $this->sqlCount = $sqlCount;
        $this->tdbmService = $tdbmService;
        $this->schema = $schema;
        $this->mainTable = $mainTable;

        $this->compute();
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

    protected function compute()
    {
        $parser = new PHPSQLParser();
        $generator = new PHPSQLCreator();
        $parsedSql = $parser->parse($this->sql);

        // 1: let's reformat the SELECT and construct our columns
        list($select, $columnDescriptors) = $this->formatSelect($parsedSql['SELECT']);
        $parsedSql['SELECT'] = $select;
        $this->processedSql = $generator->create($parsedSql);
        $this->columnDescriptors = $columnDescriptors;

        // 2: let's compute the count query if needed
        if ($this->sqlCount === null) {
            $parsedSqlCount = $this->generateParsedSqlCount($parsedSql);
            $this->processedSqlCount = $generator->create($parsedSqlCount);
        } else {
            $this->processedSqlCount = $this->sqlCount;
        }
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
            if ($noQuotes['delim'] != '.' || count($noQuotes['parts']) !== 2) {
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
        return [[
            'expr_type' => 'aggregate_function',
            'alias' => false,
            'base_expr' => 'COUNT',
            'sub_tree' => $parsedSql['SELECT'],
            'delim' => false,
        ]];
    }

    private function generateGroupedSqlCount($parsedSql)
    {
        $group = $parsedSql['GROUP'];
        unset($parsedSql['GROUP']);
        $parsedSql['SELECT'] = [[
            'expr_type' => 'aggregate_function',
            'alias' => false,
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
                'alias' => false,
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
