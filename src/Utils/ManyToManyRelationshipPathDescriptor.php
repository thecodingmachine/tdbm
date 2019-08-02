<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
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
     * ManyToManyRelationshipPathDescriptor constructor.
     * @param string $targetTable
     * @param string $pivotTable
     * @param string[] $joinForeignKeys
     * @param string[] $joinLocalKeys
     * @param string[] $whereKeys
     */
    public function __construct(string $targetTable, string $pivotTable, array $joinForeignKeys, array $joinLocalKeys, array $whereKeys)
    {
        $this->targetTable = $targetTable;
        $this->pivotTable = $pivotTable;
        $this->joinForeignKeys = $joinForeignKeys;
        $this->joinLocalKeys = $joinLocalKeys;
        $this->whereKeys = $whereKeys;
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
}
