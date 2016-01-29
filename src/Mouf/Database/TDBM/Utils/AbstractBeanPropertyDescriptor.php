<?php

namespace Mouf\Database\TDBM\Utils;

use Doctrine\DBAL\Schema\Table;

/**
 * This class represent a property in a bean (a property has a getter, a setter, etc...).
 */
abstract class AbstractBeanPropertyDescriptor
{
    /**
     * @var Table
     */
    protected $table;

    /**
     * Whether to use the more complex name in case of conflict.
     *
     * @var bool
     */
    protected $alternativeName = false;

    /**
     * @param Table $table
     */
    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    /**
     * Use the more complex name in case of conflict.
     */
    public function useAlternativeName()
    {
        $this->alternativeName = true;
    }

    /**
     * Returns the name of the class linked to this property or null if this is not a foreign key.
     *
     * @return null|string
     */
    abstract public function getClassName();

    /**
     * Returns the param annotation for this property (useful for constructor).
     *
     * @return string
     */
    abstract public function getParamAnnotation();

    public function getVariableName()
    {
        return '$' . $this->getLowerCamelCaseName();
    }

    public function getLowerCamelCaseName()
    {
        return TDBMDaoGenerator::toVariableName($this->getUpperCamelCaseName());
    }

    abstract public function getUpperCamelCaseName();

    public function getSetterName()
    {
        return 'set' . $this->getUpperCamelCaseName();
    }

    public function getGetterName()
    {
        return 'get' . $this->getUpperCamelCaseName();
    }

    /**
     * Returns the PHP code used in the ben constructor for this property.
     *
     * @return string
     */
    public function getConstructorAssignCode()
    {
        $str = '        $this->%s(%s);';

        return sprintf($str, $this->getSetterName(), $this->getVariableName());
    }

    /**
     * Returns true if the property is compulsory (and therefore should be fetched in the constructor).
     *
     * @return bool
     */
    abstract public function isCompulsory();

    /**
     * Returns true if the property is the primary key.
     *
     * @return bool
     */
    abstract public function isPrimaryKey();

    /**
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Returns the PHP code for getters and setters.
     *
     * @return string
     */
    abstract public function getGetterSetterCode();

    /**
     * Returns the part of code useful when doing json serialization.
     *
     * @return string
     */
    abstract public function getJsonSerializeCode();
}
