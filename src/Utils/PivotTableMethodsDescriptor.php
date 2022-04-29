<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Laminas\Code\Generator\DocBlockGenerator;
use function implode;
use function sprintf;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;
use TheCodingMachine\TDBM\Utils\Annotation\Annotations;
use Laminas\Code\Generator\DocBlock\Tag\ParamTag;
use Laminas\Code\Generator\DocBlock\Tag\ReturnTag;
use Laminas\Code\Generator\MethodGenerator;
use Laminas\Code\Generator\ParameterGenerator;
use function var_export;

class PivotTableMethodsDescriptor implements RelationshipMethodDescriptorInterface
{
    /** @var Table */
    private $pivotTable;

    /** @var bool */
    private $useAlternateName = false;

    /** @var ForeignKeyConstraint */
    private $localFk;

    /** @var ForeignKeyConstraint */
    private $remoteFk;
    /** @var NamingStrategyInterface */
    private $namingStrategy;
    /** @var string */
    private $beanNamespace;
    /** @var AnnotationParser */
    private $annotationParser;

    /** @var array */
    private $localAnnotations;
    /** @var array */
    private $remoteAnnotations;
    /** @var string */
    private $pathKey;
    /**
     * @var string
     */
    private $resultIteratorNamespace;

    /**
     * @param Table $pivotTable The pivot table
     * @param ForeignKeyConstraint $localFk
     * @param ForeignKeyConstraint $remoteFk
     * @param NamingStrategyInterface $namingStrategy
     */
    public function __construct(
        Table $pivotTable,
        ForeignKeyConstraint $localFk,
        ForeignKeyConstraint $remoteFk,
        NamingStrategyInterface $namingStrategy,
        AnnotationParser $annotationParser,
        string $beanNamespace,
        string $resultIteratorNamespace
    ) {
        $this->pivotTable = $pivotTable;
        $this->localFk = $localFk;
        $this->remoteFk = $remoteFk;
        $this->namingStrategy = $namingStrategy;
        $this->annotationParser = $annotationParser;
        $this->beanNamespace = $beanNamespace;
        $this->resultIteratorNamespace = $resultIteratorNamespace;

        $this->pathKey = ManyToManyRelationshipPathDescriptor::generateModelKey($this->remoteFk, $this->localFk);
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
        return 'get'.$this->getPluralName();
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
     * Returns the name of the class that will be returned by the getter (short name).
     *
     * @return string
     */
    public function getResultIteratorClassName(): string
    {
        return $this->namingStrategy->getResultIteratorClassName($this->remoteFk->getForeignTableName());
    }

    /**
     * Returns the plural name.
     *
     * @return string
     */
    private function getPluralName() : string
    {
        if ($this->isAutoPivot()) {
            $name = TDBMDaoGenerator::toPlural($this->namingStrategy->getAutoPivotEntityName($this->remoteFk, false));
            if ($this->useAlternateName) {
                $name .= 'By_'.$this->pivotTable->getName();
            }
        } elseif (!$this->useAlternateName) {
            $name = $this->remoteFk->getForeignTableName();
        } else {
            $name = $this->remoteFk->getForeignTableName().'By_'.$this->pivotTable->getName();
        }
        return TDBMDaoGenerator::toCamelCase($name);
    }

    /**
     * Returns the singular name.
     *
     * @return string
     */
    private function getSingularName() : string
    {
        if ($this->isAutoPivot()) {
            $name = $this->namingStrategy->getAutoPivotEntityName($this->remoteFk, false);
            if ($this->useAlternateName) {
                $name .= 'By_'.$this->pivotTable->getName();
            }
        } elseif (!$this->useAlternateName) {
            $name = TDBMDaoGenerator::toSingular($this->remoteFk->getForeignTableName());
        } else {
            $name = TDBMDaoGenerator::toSingular($this->remoteFk->getForeignTableName()).'By_'.$this->pivotTable->getName();
        }
        return TDBMDaoGenerator::toCamelCase($name);
    }

    private function isAutoPivot(): bool
    {
        return $this->localFk->getForeignTableName() === $this->remoteFk->getForeignTableName();
    }

    public function getManyToManyRelationshipInstantiationCode(): string
    {
        return sprintf(
            "new \\TheCodingMachine\\TDBM\\Utils\\ManyToManyRelationshipPathDescriptor(%s, %s, %s, %s, %s, %s)",
            var_export($this->remoteFk->getForeignTableName(), true),
            var_export($this->remoteFk->getLocalTableName(), true),
            $this->getArrayInlineCode($this->remoteFk->getUnquotedForeignColumns()),
            $this->getArrayInlineCode($this->remoteFk->getUnquotedLocalColumns()),
            $this->getArrayInlineCode($this->localFk->getUnquotedLocalColumns()),
            '\\' . $this->resultIteratorNamespace . '\\' . $this->getResultIteratorClassName() . '::class'
        );
    }

    /**
     * @param string[] $values
     * @return string
     */
    private function getArrayInlineCode(array $values): string
    {
        $items = [];
        foreach ($values as $value) {
            $items[] = var_export($value, true);
        }
        return '['.implode(', ', $items).']';
    }

    public function getManyToManyRelationshipKey(): string
    {
        return $this->remoteFk->getLocalTableName().".".implode("__", $this->localFk->getUnquotedLocalColumns());
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

        $pathKey = var_export($this->pathKey, true);

        $localTableName = var_export($this->remoteFk->getLocalTableName(), true);

        $getter = new MethodGenerator($this->getName());
        $getterDocBlock = new DocBlockGenerator(sprintf('Returns the list of %s associated to this bean via the %s pivot table.', $remoteBeanName, $this->pivotTable->getName()));
        $getterDocBlock->setTag(new ReturnTag([ $fqcnRemoteBeanName.'[]' ]));
        $getterDocBlock->setWordWrap(false);
        $getter->setDocBlock($getterDocBlock);
        $getter->setReturnType('array');
        $getter->setBody(sprintf('return $this->_getRelationships(%s);', $pathKey));


        $adder = new MethodGenerator('add'.$singularName);
        $adderDocBlock = new DocBlockGenerator(sprintf('Adds a relationship with %s associated to this bean via the %s pivot table.', $remoteBeanName, $this->pivotTable->getName()));
        $adderDocBlock->setTag(new ParamTag($variableName, [ $fqcnRemoteBeanName ]));
        $adderDocBlock->setWordWrap(false);
        $adder->setDocBlock($adderDocBlock);
        $adder->setReturnType('void');
        $adder->setParameter(new ParameterGenerator($variableName, $fqcnRemoteBeanName));
        $adder->setBody(sprintf('$this->addRelationship(%s, $%s);', $localTableName, $variableName));

        $remover = new MethodGenerator('remove'.$singularName);
        $removerDocBlock = new DocBlockGenerator(sprintf('Deletes the relationship with %s associated to this bean via the %s pivot table.', $remoteBeanName, $this->pivotTable->getName()));
        $removerDocBlock->setTag(new ParamTag($variableName, [ $fqcnRemoteBeanName ]));
        $removerDocBlock->setWordWrap(false);
        $remover->setDocBlock($removerDocBlock);
        $remover->setReturnType('void');
        $remover->setParameter(new ParameterGenerator($variableName, $fqcnRemoteBeanName));
        $remover->setBody(sprintf('$this->_removeRelationship(%s, $%s);', $localTableName, $variableName));

        $has = new MethodGenerator('has'.$singularName);
        $hasDocBlock = new DocBlockGenerator(sprintf('Returns whether this bean is associated with %s via the %s pivot table.', $remoteBeanName, $this->pivotTable->getName()));
        $hasDocBlock->setTag(new ParamTag($variableName, [ $fqcnRemoteBeanName ]));
        $hasDocBlock->setTag(new ReturnTag([ 'bool' ]));
        $hasDocBlock->setWordWrap(false);
        $has->setDocBlock($hasDocBlock);
        $has->setReturnType('bool');
        $has->setParameter(new ParameterGenerator($variableName, $fqcnRemoteBeanName));
        $has->setBody(sprintf('return $this->hasRelationship(%s, $%s);', $pathKey, $variableName));

        $setter = new MethodGenerator('set'.$pluralName);
        $setterDocBlock = new DocBlockGenerator(sprintf('Sets all relationships with %s associated to this bean via the %s pivot table.
Exiting relationships will be removed and replaced by the provided relationships.', $remoteBeanName, $this->pivotTable->getName()));
        $setterDocBlock->setTag(new ParamTag($pluralVariableName, [ $fqcnRemoteBeanName.'[]' ]));
        $setterDocBlock->setTag(new ReturnTag([ 'void' ]));
        $setterDocBlock->setWordWrap(false);
        $setter->setDocBlock($setterDocBlock);
        $setter->setReturnType('void');
        $setter->setParameter(new ParameterGenerator($pluralVariableName, 'array'));
        $setter->setBody(sprintf('$this->setRelationships(%s, $%s);', $pathKey, $pluralVariableName));

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
        if ($this->findRemoteAnnotation(Annotation\JsonIgnore::class) ||
            $this->findLocalAnnotation(Annotation\JsonInclude::class) ||
            $this->findLocalAnnotation(Annotation\JsonRecursive::class)) {
            return '';
        }

        /** @var Annotation\JsonFormat|null $jsonFormat */
        $jsonFormat = $this->findRemoteAnnotation(Annotation\JsonFormat::class);
        if ($jsonFormat !== null) {
            $method = $jsonFormat->method ?? 'get' . ucfirst($jsonFormat->property);
            $format = "$method()";
        } else {
            $stopRecursion = $this->findRemoteAnnotation(Annotation\JsonRecursive::class) ? '' : 'true';
            $format = "jsonSerialize($stopRecursion)";
        }
        $isIncluded = $this->findRemoteAnnotation(Annotation\JsonInclude::class) !== null;
        /** @var Annotation\JsonKey|null $jsonKey */
        $jsonKey = $this->findRemoteAnnotation(Annotation\JsonKey::class);
        $index = $jsonKey ? $jsonKey->key : lcfirst($this->getPluralName());
        $class = $this->getBeanClassName();
        $getter = $this->getName();
        $code = <<<PHP
\$array['$index'] = array_map(function ($class \$object) {
    return \$object->$format;
}, \$this->$getter());
PHP;
        if (!$isIncluded) {
            $code = preg_replace('(\n)', '\0    ', $code);
            $code = <<<PHP
if (!\$stopRecursion) {
    $code
};
PHP;
        }
        return $code;
    }

    /**
     * @return Table
     */
    public function getPivotTable(): Table
    {
        return $this->pivotTable;
    }

    /**
     * @return ForeignKeyConstraint
     */
    public function getLocalFk(): ForeignKeyConstraint
    {
        return $this->localFk;
    }

    /**
     * @return ForeignKeyConstraint
     */
    public function getRemoteFk(): ForeignKeyConstraint
    {
        return $this->remoteFk;
    }

    /**
     * @param string $type
     * @return null|object
     */
    private function findLocalAnnotation(string $type)
    {
        foreach ($this->getLocalAnnotations() as $annotations) {
            $annotation = $annotations->findAnnotation($type);
            if ($annotation !== null) {
                return $annotation;
            }
        }
        return null;
    }

    /**
     * @param string $type
     * @return null|object
     */
    private function findRemoteAnnotation(string $type)
    {
        foreach ($this->getRemoteAnnotations() as $annotations) {
            $annotation = $annotations->findAnnotation($type);
            if ($annotation !== null) {
                return $annotation;
            }
        }
        return null;
    }

    /**
     * @return Annotations[]
     */
    private function getLocalAnnotations(): array
    {
        if ($this->localAnnotations === null) {
            $this->localAnnotations = $this->getFkAnnotations($this->localFk);
        }
        return $this->localAnnotations;
    }

    /**
     * @return Annotations[]
     */
    private function getRemoteAnnotations(): array
    {
        if ($this->remoteAnnotations === null) {
            $this->remoteAnnotations = $this->getFkAnnotations($this->remoteFk);
        }
        return $this->remoteAnnotations;
    }

    /**
     * @param ForeignKeyConstraint $fk
     * @return Annotations[]
     */
    private function getFkAnnotations(ForeignKeyConstraint $fk): array
    {
        $annotations = [];
        foreach ($this->getFkColumns($fk) as $column) {
            $annotations[] = $this->annotationParser->getColumnAnnotations($column, $fk->getLocalTable());
        }
        return $annotations;
    }

    /**
     * @param ForeignKeyConstraint $fk
     * @return Column[]
     */
    private function getFkColumns(ForeignKeyConstraint $fk): array
    {
        $table = $fk->getLocalTable();
        $columns = [];
        foreach ($fk->getUnquotedLocalColumns() as $column) {
            $columns[] = $table->getColumn($column);
        }
        return $columns;
    }

    public function getCloneRule(): string
    {
        return sprintf("\$this->%s();\n", $this->getName());
    }
}
