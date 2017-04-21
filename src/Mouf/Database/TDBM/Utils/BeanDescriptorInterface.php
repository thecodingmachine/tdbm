<?php

namespace Mouf\Database\TDBM\Utils;

use Doctrine\DBAL\Schema\Table;

/**
 * This class represents a bean.
 */
interface BeanDescriptorInterface
{
    /**
     * Returns the table used to build this bean.
     *
     * @return Table
     */
    public function getTable() : Table;

    /**
     * Returns the bean class name (without the namespace).
     *
     * @return string
     */
    public function getBeanClassName(): string;

    /**
     * Returns the base bean class name (without the namespace).
     *
     * @return string
     */
    public function getBaseBeanClassName(): string;

    /**
     * Returns the extended bean class name (without the namespace), or null if the bean is not extended.
     *
     * @return null|string
     */
    public function getExtendedBeanClassName(): ?string;

    /**
     * Returns the DAO class name (without the namespace).
     *
     * @return string
     */
    public function getDaoClassName(): string;

    /**
     * Returns the base DAO class name (without the namespace).
     *
     * @return string
     */
    public function getBaseDaoClassName(): string;

    /**
     * Returns the list of properties exposed as getters and setters in this class.
     *
     * @return AbstractBeanPropertyDescriptor[]
     */
    public function getExposedProperties(): array;
}
