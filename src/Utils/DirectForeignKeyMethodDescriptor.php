<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use TheCodingMachine\TDBM\AlterableResultIterator;
use TheCodingMachine\TDBM\Schema\ForeignKey;
use TheCodingMachine\TDBM\TDBMException;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;
use TheCodingMachine\TDBM\Utils\Annotation;
use Zend\Code\Generator\AbstractMemberGenerator;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\MethodGenerator;

/**
 * Represents a method to get a list of beans from a direct foreign key pointing to our bean.
 */
class DirectForeignKeyMethodDescriptor implements MethodDescriptorInterface
{
    use ForeignKeyAnalyzerTrait;

    /**
     * @var ForeignKeyConstraint
     */
    private $foreignKey;

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
     * @var AnnotationParser
     */
    private $annotationParser;
    /**
     * @var string
     */
    private $beanNamespace;

    /**
     * @param ForeignKeyConstraint $fk The foreign key pointing to our bean
     * @param Table $mainTable The main table that is pointed to
     * @param NamingStrategyInterface $namingStrategy
     * @param AnnotationParser $annotationParser
     * @param string $beanNamespace
     */
    public function __construct(
        ForeignKeyConstraint $fk,
        Table $mainTable,
        NamingStrategyInterface $namingStrategy,
        AnnotationParser $annotationParser,
        string $beanNamespace
    ) {
        $this->foreignKey = $fk;
        $this->mainTable = $mainTable;
        $this->namingStrategy = $namingStrategy;
        $this->annotationParser = $annotationParser;
        $this->beanNamespace = $beanNamespace;
    }

    /**
     * Returns the name of the method to be generated.
     *
     * @return string
     */
    public function getName() : string
    {
        if (!$this->useAlternateName) {
            return 'get'.TDBMDaoGenerator::toCamelCase($this->foreignKey->getLocalTableName());
        } else {
            $methodName = 'get'.TDBMDaoGenerator::toCamelCase($this->foreignKey->getLocalTableName()).'By';

            $camelizedColumns = array_map([TDBMDaoGenerator::class, 'toCamelCase'], $this->foreignKey->getUnquotedLocalColumns());

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
        return $this->namingStrategy->getBeanClassName($this->foreignKey->getLocalTableName());
    }

    /**
     * Requests the use of an alternative name for this method.
     */
    public function useAlternativeName(): void
    {
        $this->useAlternateName = true;
    }

    /**
     * Returns the code of the method.
     *
     * @return MethodGenerator[]
     */
    public function getCode() : array
    {
        $beanClass = $this->getBeanClassName();
        $tdbmFk = ForeignKey::createFromFk($this->foreignKey);

        $getter = new MethodGenerator($this->getName());

        if ($this->hasLocalUniqueIndex()) {
            $getter->setDocBlock(sprintf('Returns the %s pointing to this bean via the %s column.', $beanClass, implode(', ', $this->foreignKey->getUnquotedLocalColumns())));
            $classType = '\\' . $this->beanNamespace . '\\' . $beanClass;
            $getter->getDocBlock()->setTag(new ReturnTag([$classType . '|null']));
            $getter->setReturnType('?' . $classType);

            $code = sprintf(
                'return $this->retrieveManyToOneRelationshipsStorage(%s, %s, %s)->first();',
                var_export($this->foreignKey->getLocalTableName(), true),
                var_export($tdbmFk->getCacheKey(), true),
                $this->getFilters($this->foreignKey)
            );
        } else {
            $getter->setDocBlock(sprintf('Returns the list of %s pointing to this bean via the %s column.', $beanClass, implode(', ', $this->foreignKey->getUnquotedLocalColumns())));
            $getter->getDocBlock()->setTag(new ReturnTag([
                $beanClass . '[]',
                '\\' . AlterableResultIterator::class
            ]));
            $getter->setReturnType(AlterableResultIterator::class);

            $code = sprintf(
                'return $this->retrieveManyToOneRelationshipsStorage(%s, %s, %s);',
                var_export($this->foreignKey->getLocalTableName(), true),
                var_export($tdbmFk->getCacheKey(), true),
                $this->getFilters($this->foreignKey)
            );
        }

        $getter->setBody($code);

        if ($this->isProtected()) {
            $getter->setVisibility(AbstractMemberGenerator::VISIBILITY_PROTECTED);
        }

        return [ $getter ];
    }

    private function getFilters(ForeignKeyConstraint $fk) : string
    {
        $counter = 0;
        $parameters = [];

        $fkForeignColumns = $fk->getUnquotedForeignColumns();

        foreach ($fk->getUnquotedLocalColumns() as $columnName) {
            $fkColumn = $fkForeignColumns[$counter];
            $parameters[] = sprintf('%s => $this->get(%s, %s)', var_export($fk->getLocalTableName().'.'.$columnName, true), var_export($fkColumn, true), var_export($this->foreignKey->getForeignTableName(), true));
            ++$counter;
        }
        $parametersCode = '['.implode(', ', $parameters).']';

        return $parametersCode;
    }

    /**
     * Check if the ForeignKey have an unique index
     *
     * @return bool
     */
    private function hasLocalUniqueIndex(): bool
    {
        foreach ($this->getForeignKey()->getLocalTable()->getIndexes() as $index) {
            if (
                $index->isUnique()
                && count($index->getColumns()) === count($this->getForeignKey()->getLocalColumns())
                && !array_diff($index->getColumns(), $this->getForeignKey()->getLocalColumns()) // Check for permuted columns too
            ) {
                return true;
            }
        }
        return false;
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
        /** @var Annotation\JsonCollection|null $jsonCollection */
        $jsonCollection = $this->findAnnotation(Annotation\JsonCollection::class);
        if ($jsonCollection === null) {
            return '';
        }

        /** @var Annotation\JsonFormat|null $jsonFormat */
        $jsonFormat = $this->findAnnotation(Annotation\JsonFormat::class);
        if ($jsonFormat !== null) {
            $method = $jsonFormat->method ?? 'get' . ucfirst($jsonFormat->property);
            $format = "$method()";
        } else {
            $stopRecursion = $this->findAnnotation(Annotation\JsonRecursive::class) ? '' : 'true';
            $format = "jsonSerialize($stopRecursion)";
        }
        $isIncluded = $this->findAnnotation(Annotation\JsonInclude::class) !== null;
        $index = $jsonCollection->key ?: lcfirst(TDBMDaoGenerator::toCamelCase($this->foreignKey->getLocalTableName()));
        $class = $this->getBeanClassName();
        $variableName = '$' . TDBMDaoGenerator::toVariableName($class);
        $getter = $this->getName();
        if ($this->hasLocalUniqueIndex()) {
            $code = "\$array['$index'] = (\$object = \$this->$getter()) ? \$object->$format : null;";
        } else {
            $code = <<<PHP
\$array['$index'] = array_map(function ($class $variableName) {
    return ${variableName}->$format;
}, \$this->$getter()->toArray());
PHP;
        }
        if (!$isIncluded) {
            $code = preg_replace('(\n)', '\0    ', $code);
            $code = <<<PHP
if (!\$stopRecursion) {
    $code
}
PHP;
        }
        return $code;
    }

    /**
     * @return ForeignKeyConstraint
     */
    public function getForeignKey(): ForeignKeyConstraint
    {
        return $this->foreignKey;
    }

    /**
     * Returns the table that is pointed to.
     * @return Table
     */
    public function getMainTable(): Table
    {
        return $this->mainTable;
    }

    private function isProtected(): bool
    {
        return $this->findAnnotation(Annotation\ProtectedOneToMany::class) !== null;
    }

    /**
     * @param string $type
     * @return null|object
     */
    private function findAnnotation(string $type)
    {
        foreach ($this->getAnnotations() as $annotations) {
            $annotation = $annotations->findAnnotation($type);
            if ($annotation !== null) {
                return $annotation;
            }
        }
        return null;
    }
}
