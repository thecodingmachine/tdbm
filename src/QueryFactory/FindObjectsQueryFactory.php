<?php

namespace TheCodingMachine\TDBM\QueryFactory;

use Doctrine\DBAL\Schema\Schema;
use TheCodingMachine\TDBM\OrderByAnalyzer;
use TheCodingMachine\TDBM\TDBMService;

/**
 * This class is in charge of creating the MagicQuery SQL based on parameters passed to findObjects method.
 */
class FindObjectsQueryFactory extends AbstractQueryFactory
{
    private $mainTable;
    private $additionalTablesFetch;
    private $filterString;

    public function __construct(string $mainTable, array $additionalTablesFetch, $filterString, $orderBy, TDBMService $tdbmService, Schema $schema, OrderByAnalyzer $orderByAnalyzer)
    {
        parent::__construct($tdbmService, $schema, $orderByAnalyzer, $orderBy);
        $this->mainTable = $mainTable;
        $this->additionalTablesFetch = $additionalTablesFetch;
        $this->filterString = $filterString;
    }

    protected function compute()
    {
        list($columnDescList, $columnsList, $orderString) = $this->getColumnsList($this->mainTable, $this->additionalTablesFetch, $this->orderBy);

        $sql = 'SELECT DISTINCT '.implode(', ', $columnsList).' FROM MAGICJOIN('.$this->mainTable.')';

        $pkColumnNames = $this->tdbmService->getPrimaryKeyColumns($this->mainTable);
        $pkColumnNames = array_map(function ($pkColumn) {
            return $this->tdbmService->getConnection()->quoteIdentifier($this->mainTable).'.'.$this->tdbmService->getConnection()->quoteIdentifier($pkColumn);
        }, $pkColumnNames);

        $countSql = 'SELECT COUNT(DISTINCT '.implode(', ', $pkColumnNames).') FROM MAGICJOIN('.$this->mainTable.')';

        if (!empty($this->filterString)) {
            $sql .= ' WHERE '.$this->filterString;
            $countSql .= ' WHERE '.$this->filterString;
        }

        if (!empty($orderString)) {
            $sql .= ' ORDER BY '.$orderString;
        }

        $this->magicSql = $sql;
        $this->magicSqlCount = $countSql;
        $this->columnDescList = $columnDescList;
    }
}
