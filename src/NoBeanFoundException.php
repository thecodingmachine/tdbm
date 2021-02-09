<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM;

/**
 * An exception thrown if no rows are returned when TDBMService->findObjectOrFail is called.
 */
class NoBeanFoundException extends TDBMException
{

    private string $tableName;
    private string $className;

    /**
     * @var string[]
     */
    private array $primaryKeys;

    public static function missPrimaryKeyRecord(string $tableName, array $primaryKeys, string $className, $previous) : self
    {
        $primaryKeysStringified = implode(' and ', array_map(function ($key, $value) {
            return "'".$key."' = ".$value;
        }, array_keys($primaryKeys), $primaryKeys));
        $exception = new self("No result found for query on table '".$tableName."' for ".$primaryKeysStringified, 0, $previous);
        $exception->tableName = $tableName;
        $exception->primaryKeys = $primaryKeys;
        $exception->className = $className;

        return $exception;
    }

    public static function missFilterRecord(string $tableName) : self
    {
        return new self("No result found for query on table '".$tableName."'");
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return string[]
     */
    public function getPrimaryKeys(): array
    {
        return $this->primaryKeys;
    }

}
