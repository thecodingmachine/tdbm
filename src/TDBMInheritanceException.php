<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM;

/**
 * Exception thrown when TDBM cannot find a straight path between supposedly inherited tables.
 */
class TDBMInheritanceException extends TDBMException
{
    /**
     * @param string[] $tables
     * @return TDBMInheritanceException
     */
    public static function create(array $tables): TDBMInheritanceException
    {
        return new self(sprintf('The tables (%s) cannot be linked by an inheritance relationship. Does your data set contains multiple children for one parent row? (multiple inheritance is not supported by TDBM)', implode(', ', $tables)));
    }

    /**
     * @param array[] $beanData
     */
    public static function extendException(TDBMInheritanceException $e, TDBMService $tdbmService, array $beanData): TDBMInheritanceException
    {
        $pks = [];
        foreach ($beanData as $table => $row) {
            $primaryKeyColumns = $tdbmService->getPrimaryKeyColumns($table);
            foreach ($primaryKeyColumns as $columnName) {
                if ($row[$columnName] !== null) {
                    $pks[] = $table.'.'.$columnName.' => '.var_export($row[$columnName], true);
                }
            }
        }

        return new self($e->getMessage().' (row in error: '.implode(', ', $pks).')', 0, $e);
    }
}
