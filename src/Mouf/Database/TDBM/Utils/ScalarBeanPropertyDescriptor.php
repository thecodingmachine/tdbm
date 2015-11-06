<?php


namespace Mouf\Database\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;

/**
 * This class represent a property in a bean (a property has a getter, a setter, etc...)
 */
class ScalarBeanPropertyDescriptor extends AbstractBeanPropertyDescriptor
{
    /**
     * @var Column
     */
    private $column;


    public function __construct(Table $table, Column $column) {
        parent::__construct($table);
        $this->table = $table;
        $this->column = $column;
    }

    /**
     * Returns the foreignkey the column is part of, if any. null otherwise.
     *
     * @param Column $column
     * @return ForeignKeyConstraint|null
     */
    public function getForeignKey() {
        return false;
    }

    /**
     * Returns the param annotation for this property (useful for constructor).
     *
     * @return string
     */
    public function getParamAnnotation() {
        $className = $this->getClassName();
        $paramType = $className ?: TDBMDaoGenerator::dbalTypeToPhpType($this->column->getType());

        $str = "     * @param %s %s";
        return sprintf($str, $paramType, $this->getVariableName());
    }

    public function getUpperCamelCaseName() {
        return TDBMDaoGenerator::toCamelCase($this->column->getName());
    }

    /**
     * Returns the name of the class linked to this property or null if this is not a foreign key
     * @return null|string
     */
    public function getClassName() {
        return null;
    }

    /**
     * Returns true if the property is compulsory (and therefore should be fetched in the constructor).
     * @return bool
     */
    public function isCompulsory() {
        return $this->column->getNotnull() && !$this->column->getAutoincrement();
    }

    /**
     * Returns true if the property is the primary key
     * @return bool
     */
    public function isPrimaryKey() {
        return in_array($this->column->getName(), $this->table->getPrimaryKeyColumns());
    }

    /**
     * Returns the PHP code for getters and setters
     * @return string
     */
    public function getGetterSetterCode() {

        $type = $this->column->getType();
        $normalizedType = TDBMDaoGenerator::dbalTypeToPhpType($type);

        $columnGetterName = $this->getGetterName();
        $columnSetterName = $this->getSetterName();

        if ($normalizedType == "\\DateTimeInterface") {
            $castTo = "\\DateTimeInterface ";
        } else {
            $castTo = "";
        }

        $getterAndSetterCode = '    /**
     * The getter for the "%s" column.
     *
     * @return %s
     */
    public function %s() {
        return $this->get(%s, %s);
    }

    /**
     * The setter for the "%s" column.
     *
     * @param %s $%s
     */
    public function %s(%s$%s) {
        $this->set(%s, $%s, %s);
    }

';
        return sprintf($getterAndSetterCode,
            // Getter
            $this->column->getName(),
            $normalizedType,
            $columnGetterName,
            var_export($this->column->getName(), true),
            var_export($this->table->getName(), true),
            // Setter
            $this->column->getName(),
            $normalizedType,
            $this->column->getName(),
            $columnSetterName,
            $castTo,
            $this->column->getName(),
            var_export($this->column->getName(), true),
            $this->column->getName(),
            var_export($this->table->getName(), true)
        );
    }
}
