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

    public function __construct(string $targetTable, string $pivotTable, array $joinForeignKeys, array $joinLocalKeys, array $whereKeys)
    {
        $this->targetTable = $targetTable;
        $this->pivotTable = $pivotTable;
        $this->joinForeignKeys = $joinForeignKeys;
        $this->joinLocalKeys = $joinLocalKeys;
        $this->whereKeys = $whereKeys;
    }

    /**
     * @return mixed[]
     */
    public static function generateModelArray(ForeignKeyConstraint $remoteFk, ForeignKeyConstraint $localFk): array
    {
        return [$remoteFk->getForeignTableName(), $remoteFk->getLocalTableName(), $remoteFk->getUnquotedForeignColumns(), $remoteFk->getUnquotedLocalColumns(), $localFk->getUnquotedLocalColumns()];
    }

    public static function generateModelKey(ForeignKeyConstraint $remoteFk, ForeignKeyConstraint $localFk): string
    {
        return $remoteFk->getLocalTableName().".".implode("__", $localFk->getUnquotedLocalColumns());
    }

    /**
     * @param mixed[] $modelArray
     */
    public static function createFromModelArray(array $modelArray): self
    {
        $obj = new self();
        $obj->targetTable = $modelArray[0];
        $obj->pivotTable = $modelArray[1];

        $obj->joinForeignKeys = $modelArray[2];
        $obj->joinLocalKeys= $modelArray[3];
        $obj->whereKeys = $modelArray[4];

        return $obj;
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

    public function getPivotParams(array $primaryKeys): array
    {
        $params = [];
        foreach ($primaryKeys as $key => $primaryKeyValue) {
            $params["param$key"] = $primaryKeyValue;
        }
        return $params;

    }

}