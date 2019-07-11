<?php


namespace TheCodingMachine\TDBM\Schema;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;

class ForeignKey
{
    public const FOREIGN_TABLE = 'foreignTable';
    public const LOCAL_COLUMNS = 'localColumns';
    public const FOREIGN_COLUMNS = 'foreignColumns';

    /** @var string */
    private $foreignTable;
    /** @var string[] */
    private $localColumns;
    /** @var string[] */
    private $foreignColumns;


    /**
     * @param array<string, string|array<string>> $foreignKey
     */
    public function __construct(array $foreignKey)
    {
        $this->foreignTable = $foreignKey[self::FOREIGN_TABLE];
        $this->localColumns = $foreignKey[self::LOCAL_COLUMNS];
        $this->foreignColumns = $foreignKey[self::FOREIGN_COLUMNS];
    }

    public static function createFromFk(ForeignKeyConstraint $fk): self
    {
        return new self([
            self::FOREIGN_TABLE => $fk->getForeignTableName(),
            self::LOCAL_COLUMNS => $fk->getUnquotedLocalColumns(),
            self::FOREIGN_COLUMNS => $fk->getUnquotedForeignColumns(),
        ]);
    }

    /**
     * @return array<string>
     */
    public function getUnquotedLocalColumns(): array
    {
        return $this->localColumns;
    }

    /**
     * @return array<string>
     */
    public function getUnquotedForeignColumns(): array
    {
        return $this->foreignColumns;
    }

    public function getForeignTableName(): string
    {
        return $this->foreignTable;
    }

    private $cacheKey;
    public function getCacheKey(): string
    {
        if ($this->cacheKey === null) {
            $this->cacheKey = 'from__' . implode(',', $this->getUnquotedLocalColumns()) . '__to__table__' . $this->getForeignTableName() . '__columns__' . implode(',', $this->getUnquotedForeignColumns());
        }
        return $this->cacheKey;
    }
}
