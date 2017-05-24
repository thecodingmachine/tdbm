<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;

/**
 * This class represent a property in a bean that points to another table.
 */
class ObjectBeanPropertyDescriptor extends AbstractBeanPropertyDescriptor
{
    /**
     * @var ForeignKeyConstraint
     */
    private $foreignKey;

    /**
     * @var SchemaAnalyzer
     */
    private $schemaAnalyzer;

    /**
     * ObjectBeanPropertyDescriptor constructor.
     * @param Table $table
     * @param ForeignKeyConstraint $foreignKey
     * @param SchemaAnalyzer $schemaAnalyzer
     * @param NamingStrategyInterface $namingStrategy
     */
    public function __construct(Table $table, ForeignKeyConstraint $foreignKey, SchemaAnalyzer $schemaAnalyzer, NamingStrategyInterface $namingStrategy)
    {
        parent::__construct($table, $namingStrategy);
        $this->foreignKey = $foreignKey;
        $this->schemaAnalyzer = $schemaAnalyzer;
    }

    /**
     * Returns the foreignkey the column is part of, if any. null otherwise.
     *
     * @return ForeignKeyConstraint|null
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * Returns the name of the class linked to this property or null if this is not a foreign key.
     *
     * @return null|string
     */
    public function getClassName(): ?string
    {
        return $this->namingStrategy->getBeanClassName($this->foreignKey->getForeignTableName());
    }

    /**
     * Returns the PHP type for the property (it can be a scalar like int, bool, or class names, like \DateTimeInterface, App\Bean\User....)
     *
     * @return string
     */
    public function getPhpType(): string
    {
        return $this->getClassName();
    }


    /**
     * Returns the param annotation for this property (useful for constructor).
     *
     * @return string
     */
    public function getParamAnnotation()
    {
        $str = '     * @param %s %s';

        return sprintf($str, $this->getClassName(), $this->getVariableName());
    }

    /**
     * Returns true if the property is compulsory (and therefore should be fetched in the constructor).
     *
     * @return bool
     */
    public function isCompulsory()
    {
        // Are all columns nullable?
        $localColumnNames = $this->foreignKey->getLocalColumns();

        foreach ($localColumnNames as $name) {
            $column = $this->table->getColumn($name);
            if ($column->getNotnull()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if the property has a default value.
     *
     * @return bool
     */
    public function hasDefault()
    {
        return false;
    }

    /**
     * Returns the code that assigns a value to its default value.
     *
     * @return string
     *
     * @throws \TDBMException
     */
    public function assignToDefaultCode()
    {
        throw new \TDBMException('Foreign key based properties cannot be assigned a default value.');
    }

    /**
     * Returns true if the property is the primary key.
     *
     * @return bool
     */
    public function isPrimaryKey()
    {
        $fkColumns = $this->foreignKey->getLocalColumns();
        sort($fkColumns);

        $pkColumns = $this->table->getPrimaryKeyColumns();
        sort($pkColumns);

        return $fkColumns == $pkColumns;
    }

    /**
     * Returns the PHP code for getters and setters.
     *
     * @return string
     */
    public function getGetterSetterCode()
    {
        $tableName = $this->table->getName();
        $getterName = $this->getGetterName();
        $setterName = $this->getSetterName();
        $isNullable = !$this->isCompulsory();

        $referencedBeanName = $this->namingStrategy->getBeanClassName($this->foreignKey->getForeignTableName());

        $str = '    /**
     * Returns the '.$referencedBeanName.' object bound to this object via the '.implode(' and ', $this->foreignKey->getLocalColumns()).' column.
     *
     * @return '.$referencedBeanName.($isNullable?'|null':'').'
     */
    public function '.$getterName.'(): '.($isNullable?'?':'').$referencedBeanName.'
    {
        return $this->getRef('.var_export($this->foreignKey->getName(), true).', '.var_export($tableName, true).');
    }

    /**
     * The setter for the '.$referencedBeanName.' object bound to this object via the '.implode(' and ', $this->foreignKey->getLocalColumns()).' column.
     *
     * @param '.$referencedBeanName.($isNullable?'|null':'').' $object
     */
    public function '.$setterName.'('.($isNullable?'?':'').$referencedBeanName.' $object) : void
    {
        $this->setRef('.var_export($this->foreignKey->getName(), true).', $object, '.var_export($tableName, true).');
    }

';

        return $str;
    }

    /**
     * Returns the part of code useful when doing json serialization.
     *
     * @return string
     */
    public function getJsonSerializeCode()
    {
        return '        if (!$stopRecursion) {
            $object = $this->'.$this->getGetterName().'();
            $array['.var_export($this->namingStrategy->getJsonProperty($this), true).'] = $object ? $object->jsonSerialize(true) : null;
        }
';
    }

}
