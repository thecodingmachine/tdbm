<?php

namespace Mouf\Database\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;

/**
 * This class represent a property in a bean (a property has a getter, a setter, etc...).
 */
class ScalarBeanPropertyDescriptor extends AbstractBeanPropertyDescriptor
{
    /**
     * @var Column
     */
    private $column;

    public function __construct(Table $table, Column $column)
    {
        parent::__construct($table);
        $this->table = $table;
        $this->column = $column;
    }

    /**
     * Returns the foreign-key the column is part of, if any. null otherwise.
     *
     * @return ForeignKeyConstraint|null
     */
    public function getForeignKey()
    {
        return false;
    }

    /**
     * Returns the param annotation for this property (useful for constructor).
     *
     * @return string
     */
    public function getParamAnnotation()
    {
        $className = $this->getClassName();
        $paramType = $className ?: TDBMDaoGenerator::dbalTypeToPhpType($this->column->getType());

        $str = '     * @param %s %s';

        return sprintf($str, $paramType, $this->getVariableName());
    }

    public function getUpperCamelCaseName()
    {
        return TDBMDaoGenerator::toCamelCase($this->column->getName());
    }

    /**
     * Returns the name of the class linked to this property or null if this is not a foreign key.
     *
     * @return null|string
     */
    public function getClassName()
    {
        return;
    }

    /**
     * Returns true if the property is compulsory (and therefore should be fetched in the constructor).
     *
     * @return bool
     */
    public function isCompulsory()
    {
        return $this->column->getNotnull() && !$this->column->getAutoincrement() && $this->column->getDefault() === null;
    }

    /**
     * Returns true if the property has a default value.
     *
     * @return bool
     */
    public function hasDefault()
    {
        return $this->column->getDefault() !== null;
    }

    /**
     * Returns the code that assigns a value to its default value.
     *
     * @return string
     */
    public function assignToDefaultCode()
    {
        $str = '        $this->%s(%s);';

        $default = $this->column->getDefault();

        if (strtoupper($default) === 'CURRENT_TIMESTAMP') {
            $defaultCode = 'new \DateTimeImmutable()';
        } else {
            $defaultCode = var_export($this->column->getDefault(), true);
        }

        return sprintf($str, $this->getSetterName(), $defaultCode);
    }

    /**
     * Returns true if the property is the primary key.
     *
     * @return bool
     */
    public function isPrimaryKey()
    {
        return in_array($this->column->getName(), $this->table->getPrimaryKeyColumns());
    }

    /**
     * Returns the PHP code for getters and setters.
     *
     * @return string
     */
    public function getGetterSetterCode()
    {
        $type = $this->column->getType();
        $normalizedType = TDBMDaoGenerator::dbalTypeToPhpType($type);

        $columnGetterName = $this->getGetterName();
        $columnSetterName = $this->getSetterName();

        // A column type can be forced if it is not nullable and not auto-incrmentable (for auto-increment columns, we can get "null" as long as the bean is not saved).
        $canForceGetterReturnType = $this->column->getNotnull() && !$this->column->getAutoincrement();

        $getterAndSetterCode = '    /**
     * The getter for the "%s" column.
     *
     * @return %s
     */
    public function %s()%s
    {
        return $this->get(%s, %s);
    }

    /**
     * The setter for the "%s" column.
     *
     * @param %s $%s
     */
    public function %s(%s $%s)
    {
        $this->set(%s, $%s, %s);
    }

';

        return sprintf($getterAndSetterCode,
            // Getter
            $this->column->getName(),
            $normalizedType.($canForceGetterReturnType ? '' : '|null'),
            $columnGetterName,
            ($canForceGetterReturnType ? ' : '.$normalizedType : ''),
            var_export($this->column->getName(), true),
            var_export($this->table->getName(), true),
            // Setter
            $this->column->getName(),
            $normalizedType,
            $this->column->getName(),
            $columnSetterName,
            $normalizedType,
            //$castTo,
            $this->column->getName().($this->column->getNotnull() ? '' : ' = null'),
            var_export($this->column->getName(), true),
            $this->column->getName(),
            var_export($this->table->getName(), true)
        );
    }

    /**
     * Returns the part of code useful when doing json serialization.
     *
     * @return string
     */
    public function getJsonSerializeCode()
    {
        $type = $this->column->getType();
        $normalizedType = TDBMDaoGenerator::dbalTypeToPhpType($type);

        if ($normalizedType == '\\DateTimeInterface') {
            return '        $array['.var_export($this->getLowerCamelCaseName(), true).'] = ($this->'.$this->getGetterName().'() === null)?null:$this->'.$this->getGetterName()."()->format('c');\n";
        } else {
            return '        $array['.var_export($this->getLowerCamelCaseName(), true).'] = $this->'.$this->getGetterName()."();\n";
        }
    }

    /**
     * Returns the column name.
     *
     * @return string
     */
    public function getColumnName()
    {
        return $this->column->getName();
    }
}
