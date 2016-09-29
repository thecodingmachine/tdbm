<?php

namespace Mouf\Database\TDBM\QueryFactory;

use Doctrine\DBAL\Schema\Schema;
use Mouf\Database\TDBM\OrderByAnalyzer;
use Mouf\Database\TDBM\TDBMService;

/**
 * This class is in charge of creating the MagicQuery SQL based on parameters passed to findObjects method.
 */
class FindObjectsQueryFactory extends AbstractQueryFactory
{
    private $mainTable;
    private $additionalTablesFetch;
    private $filterString;
    private $orderBy;

    private $magicSql;
    private $magicSqlCount;
    private $columnDescList;

    public function __construct(string $mainTable, array $additionalTablesFetch, $filterString, $orderBy, TDBMService $tdbmService, Schema $schema, OrderByAnalyzer $orderByAnalyzer)
    {
        parent::__construct($tdbmService, $schema, $orderByAnalyzer);
        $this->mainTable = $mainTable;
        $this->additionalTablesFetch = $additionalTablesFetch;
        $this->filterString = $filterString;
        $this->orderBy = $orderBy;
    }

    private function compute()
    {
        list($columnDescList, $columnsList, $orderString) = $this->getColumnsList($this->mainTable, $this->additionalTablesFetch, $this->orderBy);

        $sql = 'SELECT DISTINCT '.implode(', ', $columnsList).' FROM MAGICJOIN('.$this->mainTable.')';

        $pkColumnNames = $this->schema->getTable($this->mainTable)->getPrimaryKeyColumns();
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
}
