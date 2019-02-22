<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use function sprintf;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;

class PivotTableMethodsDescriptor implements MethodDescriptorInterface
{
    /**
     * @var Table
     */
    private $pivotTable;

    private $useAlternateName = false;

    /**
     * @var ForeignKeyConstraint
     */
    private $localFk;

    /**
     * @var ForeignKeyConstraint
     */
    private $remoteFk;
    /**
     * @var NamingStrategyInterface
     */
    private $namingStrategy;
    /**
     * @var string
     */
    private $beanNamespace;

    /**
     * @param Table $pivotTable The pivot table
     * @param ForeignKeyConstraint $localFk
     * @param ForeignKeyConstraint $remoteFk
     * @param NamingStrategyInterface $namingStrategy
     */
    public function __construct(Table $pivotTable, ForeignKeyConstraint $localFk, ForeignKeyConstraint $remoteFk, NamingStrategyInterface $namingStrategy, string $beanNamespace)
    {
        $this->pivotTable = $pivotTable;
        $this->localFk = $localFk;
        $this->remoteFk = $remoteFk;
        $this->namingStrategy = $namingStrategy;
        $this->beanNamespace = $beanNamespace;
    }

    /**
     * Requests the use of an alternative name for this method.
     */
    public function useAlternativeName(): void
    {
        $this->useAlternateName = true;
    }

    /**
     * Returns the name of the method to be generated.
     *
     * @return string
     */
    public function getName() : string
    {
        if (!$this->useAlternateName) {
            return 'get'.TDBMDaoGenerator::toCamelCase($this->remoteFk->getForeignTableName());
        } else {
            return 'get'.TDBMDaoGenerator::toCamelCase($this->remoteFk->getForeignTableName()).'By'.TDBMDaoGenerator::toCamelCase($this->pivotTable->getName());
        }
    }

    /**
     * Returns the name of the class that will be returned by the getter (short name).
     *
     * @return string
     */
    public function getBeanClassName(): string
    {
        return $this->namingStrategy->getBeanClassName($this->remoteFk->getForeignTableName());
    }

    /**
     * Returns the plural name.
     *
     * @return string
     */
    private function getPluralName() : string
    {
        if (!$this->useAlternateName) {
            return TDBMDaoGenerator::toCamelCase($this->remoteFk->getForeignTableName());
        } else {
            return TDBMDaoGenerator::toCamelCase($this->remoteFk->getForeignTableName()).'By'.TDBMDaoGenerator::toCamelCase($this->pivotTable->getName());
        }
    }

    /**
     * Returns the singular name.
     *
     * @return string
     */
    private function getSingularName() : string
    {
        if (!$this->useAlternateName) {
            return TDBMDaoGenerator::toCamelCase(TDBMDaoGenerator::toSingular($this->remoteFk->getForeignTableName()));
        } else {
            return TDBMDaoGenerator::toCamelCase(TDBMDaoGenerator::toSingular($this->remoteFk->getForeignTableName())).'By'.TDBMDaoGenerator::toCamelCase($this->pivotTable->getName());
        }
    }

    /**
     * Returns the code of the method.
     *
     * @return MethodGenerator[]
     */
    public function getCode() : array
    {
        $singularName = $this->getSingularName();
        $pluralName = $this->getPluralName();
        $remoteBeanName = $this->getBeanClassName();
        $variableName = TDBMDaoGenerator::toVariableName($remoteBeanName);
        $fqcnRemoteBeanName = '\\'.$this->beanNamespace.'\\'.$remoteBeanName;
        $pluralVariableName = $variableName.'s';

        $getter = new MethodGenerator('get'.$pluralName);
        $getter->setDocBlock(sprintf('Returns the list of %s associated to this bean via the %s pivot table.', $remoteBeanName, $this->pivotTable->getName()));
        $getter->getDocBlock()->setTag(new ReturnTag([ $fqcnRemoteBeanName.'[]' ]));
        $getter->setReturnType('array');
        $getter->setBody(sprintf('return $this->_getRelationships(%s);', var_export($this->remoteFk->getLocalTableName(), true)));


        $adder = new MethodGenerator('add'.$singularName);
        $adder->setDocBlock(sprintf('Adds a relationship with %s associated to this bean via the %s pivot table.', $remoteBeanName, $this->pivotTable->getName()));
        $adder->getDocBlock()->setTag(new ParamTag($variableName, [ $fqcnRemoteBeanName ]));
        $adder->setReturnType('void');
        $adder->setParameter(new ParameterGenerator($variableName, $fqcnRemoteBeanName));
        $adder->setBody(sprintf('$this->addRelationship(%s, $%s);', var_export($this->remoteFk->getLocalTableName(), true), $variableName));

        $remover = new MethodGenerator('remove'.$singularName);
        $remover->setDocBlock(sprintf('Deletes the relationship with %s associated to this bean via the %s pivot table.', $remoteBeanName, $this->pivotTable->getName()));
        $remover->getDocBlock()->setTag(new ParamTag($variableName, [ $fqcnRemoteBeanName ]));
        $remover->setReturnType('void');
        $remover->setParameter(new ParameterGenerator($variableName, $fqcnRemoteBeanName));
        $remover->setBody(sprintf('$this->_removeRelationship(%s, $%s);', var_export($this->remoteFk->getLocalTableName(), true), $variableName));

        $has = new MethodGenerator('has'.$singularName);
        $has->setDocBlock(sprintf('Returns whether this bean is associated with %s via the %s pivot table.', $remoteBeanName, $this->pivotTable->getName()));
        $has->getDocBlock()->setTag(new ParamTag($variableName, [ $fqcnRemoteBeanName ]));
        $has->getDocBlock()->setTag(new ReturnTag([ 'bool' ]));
        $has->setReturnType('bool');
        $has->setParameter(new ParameterGenerator($variableName, $fqcnRemoteBeanName));
        $has->setBody(sprintf('return $this->hasRelationship(%s, $%s);', var_export($this->remoteFk->getLocalTableName(), true), $variableName));

        $setter = new MethodGenerator('set'.$pluralName);
        $setter->setDocBlock(sprintf('Sets all relationships with %s associated to this bean via the %s pivot table.
Exiting relationships will be removed and replaced by the provided relationships.', $remoteBeanName, $this->pivotTable->getName()));
        $setter->getDocBlock()->setTag(new ParamTag($pluralVariableName, [ $fqcnRemoteBeanName.'[]' ]));
        $setter->getDocBlock()->setTag(new ReturnTag([ 'void' ]));
        $setter->setReturnType('void');
        $setter->setParameter(new ParameterGenerator($pluralVariableName, 'array'));
        $setter->setBody(sprintf('$this->setRelationships(%s, $%s);', var_export($this->remoteFk->getLocalTableName(), true), $pluralVariableName));

        return [ $getter, $adder, $remover, $has, $setter ];
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
        $remoteBeanName = $this->getBeanClassName();
        $variableName = '$'.TDBMDaoGenerator::toVariableName($remoteBeanName);

        return 'if (!$stopRecursion) {
    $array[\''.lcfirst($this->getPluralName()).'\'] = array_map(function ('.$remoteBeanName.' '.$variableName.') {
        return '.$variableName.'->jsonSerialize(true);
    }, $this->'.$this->getName().'());
}
';
    }
}
