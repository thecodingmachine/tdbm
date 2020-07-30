<?php


namespace TheCodingMachine\TDBM\Schema;

class ForeignKeys
{
    /**
     * @var array<string, array{foreignTable: string, localColumns: string[], foreignColumns: string[]}>
     */
    private $foreignKeys;

    /**
     * @var array
     */
    private $foreignKey;

    /**
     * @param array<string, array{foreignTable: string, localColumns: string[], foreignColumns: string[]}> $foreignKeys
     */
    public function __construct(array $foreignKeys)
    {
        $this->foreignKeys = $foreignKeys;
        $this->foreignKey = [];
    }

    public function getForeignKey(string $fkName): ForeignKey
    {
        if (!isset($this->foreignKey[$fkName])) {
            $this->foreignKey[$fkName] = new ForeignKey($this->foreignKeys[$fkName]);
        }
        return $this->foreignKey[$fkName];
    }
}
