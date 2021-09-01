<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use TheCodingMachine\TDBM\ResultIterator;
use TheCodingMachine\TDBM\TDBMInvalidArgumentException;

/**
 * @internal
 */
class ManyToManyRelationshipPathDescriptor
{
    /**
     * @var string Unquoted identifier of the target table
     */
    private $targetTable;
    /**
     * @var string Unquoted identifier of the pivot table
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
     * @var class-string
     */
    private $resultIteratorClass;

    /**
     * @param string[] $joinForeignKeys
     * @param string[] $joinLocalKeys
     * @param string[] $whereKeys
     * @param class-string $resultIteratorClass
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
        return $remoteFk->getLocalTableName() . "." . implode("__", $localFk->getUnquotedLocalColumns());
    }

    public function getPivotName(): string
    {
        return $this->pivotTable;
    }

    public function getTargetName(): string
    {
        return $this->targetTable;
    }

    /**
     * Get the `FROM` clause of the query.
     *
     * This may have issue handling namespaced table (e.g. mydb.table)
     */
    public function getPivotFrom(AbstractPlatform $platform): string
    {
        $mainTable = $this->targetTable;
        $pivotTable = $this->pivotTable;

        $join = [];
        foreach ($this->joinForeignKeys as $key => $column) {
            $join[] = sprintf(
                '%s.%s = %s.%s',
                $platform->quoteIdentifier($mainTable),
                $platform->quoteIdentifier($column),
                $platform->quoteIdentifier($pivotTable),
                $platform->quoteIdentifier($this->joinLocalKeys[$key])
            );
        }

        return $platform->quoteIdentifier($mainTable) . ' JOIN ' . $platform->quoteIdentifier($pivotTable) . ' ON ' . implode(' AND ', $join);
    }

    /**
     * Get the `WHERE` clause of the query.
     *
     * This may have issue handling namespaced table (e.g. mydb.table)
     */
    public function getPivotWhere(AbstractPlatform $platform): string
    {
        $paramList = [];
        foreach ($this->whereKeys as $key => $column) {
            $paramList[] = sprintf('%s.%s = :param%s', $platform->quoteIdentifier($this->pivotTable), $platform->quoteIdentifier($column), $key);
        }
        return implode(' AND ', $paramList);
    }

    /**
     * @param string[] $primaryKeys
     * @return string[]
     */
    public function getPivotParams(array $primaryKeys): array
    {
        $params = [];
        foreach ($primaryKeys as $key => $primaryKeyValue) {
            $params['param' . $key] = $primaryKeyValue;
        }
        return $params;
    }

    /**
     * @return class-string
     */
    public function getResultIteratorClass(): string
    {
        return $this->resultIteratorClass;
    }
}
