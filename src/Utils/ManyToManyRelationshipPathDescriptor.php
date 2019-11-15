<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use TheCodingMachine\TDBM\ResultIterator;
use TheCodingMachine\TDBM\TDBMInvalidArgumentException;
use function var_export;

class ManyToManyRelationshipPathDescriptor
{

    /**
     * @var string
     */
    private $targetTable;
    /**
     * @var string
     */
    private $pivotTable;
    /**
     * @var array
     */
    private $joinForeignKeys;
    /**
     * @var array
     */
    private $joinLocalKeys;
    /**
     * @var array
     */
    private $whereKeys;
    /**
     * @var string
     */
    private $resultIteratorClass;

    /**
     * ManyToManyRelationshipPathDescriptor constructor.
     * @param string $targetTable
     * @param string $pivotTable
     * @param string[] $joinForeignKeys
     * @param string[] $joinLocalKeys
     * @param string[] $whereKeys
     */
    public function __construct(string $targetTable, string $pivotTable, array $joinForeignKeys, array $joinLocalKeys, array $whereKeys, string $resultIteratorClass)
    {
        assert(is_a($resultIteratorClass, ResultIterator::class, true), new TDBMInvalidArgumentException('$resultIteratorClass should be a `'. ResultIterator::class. '`. `' . $resultIteratorClass . '` provided.'));
        $this->targetTable = $targetTable;
        $this->pivotTable = $pivotTable;
        $this->joinForeignKeys = $joinForeignKeys;
        $this->joinLocalKeys = $joinLocalKeys;
        $this->whereKeys = $whereKeys;
        $this->resultIteratorClass = $resultIteratorClass;
    }

    public static function generateModelKey(ForeignKeyConstraint $remoteFk, ForeignKeyConstraint $localFk): string
    {
        return $remoteFk->getLocalTableName().".".implode("__", $localFk->getUnquotedLocalColumns());
    }

    public function getPivotName(): string
    {
        return $this->pivotTable;
    }

    public function getTargetName(): string
    {
        return $this->targetTable;
    }

    public function getPivotFrom(): string
    {
        $mainTable = $this->targetTable;
        $pivotTable = $this->pivotTable;

        $join = [];
        foreach ($this->joinForeignKeys as $key => $column) {
            $join[] = $mainTable.'.'.$column.' = pivot.'.$this->joinLocalKeys[$key];
        }

        return $mainTable.' JOIN '.$pivotTable.' pivot ON '.implode(' AND ', $join);
    }

    public function getPivotWhere(): string
    {
        $paramList = [];
        foreach ($this->whereKeys as $key => $column) {
            $paramList[] = ' pivot.'.$column." = :param$key";
        }
        return implode(" AND ", $paramList);
    }

    /**
     * @param string[] $primaryKeys
     * @return string[]
     */
    public function getPivotParams(array $primaryKeys): array
    {
        $params = [];
        foreach ($primaryKeys as $key => $primaryKeyValue) {
            $params["param$key"] = $primaryKeyValue;
        }
        return $params;
    }

    public function getResultIteratorClass(): string
    {
        return $this->resultIteratorClass;
    }
}
