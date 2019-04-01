<?php


namespace TheCodingMachine\TDBM\Schema;


use Doctrine\DBAL\Schema\ForeignKeyConstraint;

class ForeignKey
{
    public const FOREIGN_TABLE = 'foreignTable';
    public const LOCAL_COLUMNS = 'localColumns';
    public const FOREIGN_COLUMNS = 'foreignColumns';

    /**
     * @var array<string, string|array<string>>
     */
    private $foreignKey;

    /**
     * @param array<string, string|array<string>> $foreignKey
     */
    public function __construct(array $foreignKey)
    {
        $this->foreignKey = $foreignKey;
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
        return $this->foreignKey[self::LOCAL_COLUMNS];
    }

    /**
     * @return array<string>
     */
    public function getUnquotedForeignColumns(): array
    {
        return $this->foreignKey[self::FOREIGN_COLUMNS];
    }

    public function getForeignTableName(): string
    {
        return $this->foreignKey[self::FOREIGN_TABLE];
    }

    public function getCacheKey(): string
    {
        return 'from__'.implode(',', $this->getUnquotedLocalColumns()) . '__to__table__' . $this->getForeignTableName() . '__columns__' . implode(',', $this->getUnquotedForeignColumns());
    }
}
