<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;

/**
 * Represents a method to get a list of beans from a direct foreign key pointing to our bean.
 */
class DirectForeignKeyMethodDescriptor implements MethodDescriptorInterface
{
    /**
     * @var ForeignKeyConstraint
     */
    private $fk;

    private $useAlternateName = false;
    /**
     * @var Table
     */
    private $mainTable;
    /**
     * @var NamingStrategyInterface
     */
    private $namingStrategy;

    /**
     * @param ForeignKeyConstraint $fk The foreign key pointing to our bean
     * @param Table $mainTable The main table that is pointed to
     * @param NamingStrategyInterface $namingStrategy
     */
    public function __construct(ForeignKeyConstraint $fk, Table $mainTable, NamingStrategyInterface $namingStrategy)
    {
        $this->fk = $fk;
        $this->mainTable = $mainTable;
        $this->namingStrategy = $namingStrategy;
    }

    /**
     * Returns the name of the method to be generated.
     *
     * @return string
     */
    public function getName() : string
    {
        if (!$this->useAlternateName) {
            return 'get'.TDBMDaoGenerator::toCamelCase($this->fk->getLocalTableName());
        } else {
            $methodName = 'get'.TDBMDaoGenerator::toCamelCase($this->fk->getLocalTableName()).'By';

            $camelizedColumns = array_map([TDBMDaoGenerator::class, 'toCamelCase'], $this->fk->getUnquotedLocalColumns());

            $methodName .= implode('And', $camelizedColumns);

            return $methodName;
        }
    }

    /**
     * Returns the name of the class that will be returned by the getter (short name).
     *
     * @return string
     */
    public function getBeanClassName(): string
    {
        return $this->namingStrategy->getBeanClassName($this->fk->getLocalTableName());
    }

    /**
     * Requests the use of an alternative name for this method.
     */
    public function useAlternativeName()
    {
        $this->useAlternateName = true;
    }

    /**
     * Returns the code of the method.
     *
     * @return string
     */
    public function getCode() : string
    {
        $code = '';

        $getterCode = '    /**
     * Returns the list of %s pointing to this bean via the %s column.
     *
     * @return %s[]|AlterableResultIterator
     */
    public function %s() : AlterableResultIterator
    {
        return $this->retrieveManyToOneRelationshipsStorage(%s, %s, %s, %s);
    }

';

        $beanClass = $this->getBeanClassName();
        $code .= sprintf(
            $getterCode,
            $beanClass,
            implode(', ', $this->fk->getUnquotedLocalColumns()),
            $beanClass,
            $this->getName(),
            var_export($this->fk->getLocalTableName(), true),
            var_export($this->fk->getName(), true),
            var_export($this->fk->getLocalTableName(), true),
            $this->getFilters($this->fk)
        );

        return $code;
    }

    private function getFilters(ForeignKeyConstraint $fk) : string
    {
        $counter = 0;
        $parameters = [];

        $pkColumns = $this->mainTable->getPrimaryKey()->getUnquotedColumns();

        foreach ($fk->getUnquotedLocalColumns() as $columnName) {
            $pkColumn = $pkColumns[$counter];
            $parameters[] = sprintf('%s => $this->get(%s, %s)', var_export($fk->getLocalTableName().'.'.$columnName, true), var_export($pkColumn, true), var_export($this->fk->getForeignTableName(), true));
            ++$counter;
        }
        $parametersCode = '['.implode(', ', $parameters).']';

        return $parametersCode;
    }

    /**
     * Returns an array of classes that needs a "use" for this method.
     *
     * @return string[]
     */
    public function getUsedClasses() : array
    {
        return [$this->getBeanClassName()];
    }

    /**
     * Returns the code to past in jsonSerialize.
     *
     * @return string
     */
    public function getJsonSerializeCode() : string
    {
        return '';
    }
}
