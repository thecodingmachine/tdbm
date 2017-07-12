<?php


namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;

/**
 * Generates bean / dao / property / method names from the database model.
 */
interface NamingStrategyInterface
{
    /**
     * Returns the bean class name from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getBeanClassName(string $tableName) : string;

    /**
     * Returns the base bean class name from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getBaseBeanClassName(string $tableName) : string;

    /**
     * Returns the name of the DAO class from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getDaoClassName(string $tableName) : string;

    /**
     * Returns the name of the base DAO class from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getBaseDaoClassName(string $tableName) : string;

    /**
     * Returns the class name for the DAO factory.
     *
     * @return string
     */
    public function getDaoFactoryClassName() : string;

    /**
     * Returns the getter name generated for the property passed in parameter.
     *
     * @param AbstractBeanPropertyDescriptor $property
     * @return string
     */
    public function getGetterName(AbstractBeanPropertyDescriptor $property): string;

    /**
     * Returns the setter name generated for the property passed in parameter.
     *
     * @param AbstractBeanPropertyDescriptor $property
     * @return string
     */
    public function getSetterName(AbstractBeanPropertyDescriptor $property): string;

    /**
     * Returns the variable name used in the setter generated for the property passed in parameter.
     *
     * @param AbstractBeanPropertyDescriptor $property
     * @return string
     */
    public function getVariableName(AbstractBeanPropertyDescriptor $property): string;

    /**
     * Returns the label of the JSON property for the property passed in parameter.
     *
     * @param AbstractBeanPropertyDescriptor $property
     * @return string
     */
    public function getJsonProperty(AbstractBeanPropertyDescriptor $property): string;

    /**
     * Returns the name of the find method attached to an index.
     *
     * @param Index $index
     * @param AbstractBeanPropertyDescriptor[] $elements The list of properties in the index.
     * @return string
     */
    public function getFindByIndexMethodName(Index $index, array $elements): string;
}
