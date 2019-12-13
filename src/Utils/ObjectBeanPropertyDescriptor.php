<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use TheCodingMachine\TDBM\Schema\ForeignKey;
use TheCodingMachine\TDBM\TDBMException;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;
use TheCodingMachine\TDBM\Utils\Annotation;
use Zend\Code\Generator\AbstractMemberGenerator;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;

/**
 * This class represent a property in a bean that points to another table.
 */
class ObjectBeanPropertyDescriptor extends AbstractBeanPropertyDescriptor
{
    use ForeignKeyAnalyzerTrait;

    /**
     * @var ForeignKeyConstraint
     */
    private $foreignKey;
    /**
     * @var string
     */
    private $beanNamespace;
    /**
     * @var BeanDescriptor
     */
    private $foreignBeanDescriptor;

    /**
     * ObjectBeanPropertyDescriptor constructor.
     * @param Table $table
     * @param ForeignKeyConstraint $foreignKey
     * @param NamingStrategyInterface $namingStrategy
     * @param string $beanNamespace
     * @param AnnotationParser $annotationParser
     * @param BeanDescriptor $foreignBeanDescriptor The BeanDescriptor of FK foreign table
     */
    public function __construct(
        Table $table,
        ForeignKeyConstraint $foreignKey,
        NamingStrategyInterface $namingStrategy,
        string $beanNamespace,
        AnnotationParser $annotationParser,
        BeanDescriptor $foreignBeanDescriptor
    ) {
        parent::__construct($table, $namingStrategy);
        $this->foreignKey = $foreignKey;
        $this->beanNamespace = $beanNamespace;
        $this->annotationParser = $annotationParser;
        $this->table = $table;
        $this->namingStrategy = $namingStrategy;
        $this->foreignBeanDescriptor = $foreignBeanDescriptor;
    }

    /**
     * Returns the foreignkey the column is part of, if any. null otherwise.
     *
     * @return ForeignKeyConstraint
     */
    public function getForeignKey(): ForeignKeyConstraint
    {
        return $this->foreignKey;
    }

    /**
     * Returns the name of the class linked to this property or null if this is not a foreign key.
     *
     * @return string
     */
    public function getClassName(): string
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
        return '\\' . $this->beanNamespace . '\\' . $this->getClassName();
    }

    /**
     * Returns true if the property is compulsory (and therefore should be fetched in the constructor).
     *
     * @return bool
     */
    public function isCompulsory(): bool
    {
        // Are all columns nullable?
        foreach ($this->getLocalColumns() as $column) {
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
    public function hasDefault(): bool
    {
        return false;
    }

    /**
     * Returns the code that assigns a value to its default value.
     *
     * @return string
     *
     * @throws TDBMException
     */
    public function assignToDefaultCode(): string
    {
        throw new TDBMException('Foreign key based properties cannot be assigned a default value.');
    }

    /**
     * Returns true if the property is the primary key.
     *
     * @return bool
     */
    public function isPrimaryKey(): bool
    {
        $fkColumns = $this->foreignKey->getUnquotedLocalColumns();
        sort($fkColumns);

        $pkColumns = TDBMDaoGenerator::getPrimaryKeyColumnsOrFail($this->table);
        sort($pkColumns);

        return $fkColumns == $pkColumns;
    }

    /**
     * Returns the PHP code for getters and setters.
     *
     * @return MethodGenerator[]
     */
    public function getGetterSetterCode(): array
    {
        $tableName = $this->table->getName();
        $getterName = $this->getGetterName();
        $setterName = $this->getSetterName();
        $isNullable = !$this->isCompulsory();

        $referencedBeanName = $this->namingStrategy->getBeanClassName($this->foreignKey->getForeignTableName());

        $getter = new MethodGenerator($getterName);
        $getter->setDocBlock(new DocBlockGenerator('Returns the ' . $referencedBeanName . ' object bound to this object via the ' . implode(' and ', $this->foreignKey->getUnquotedLocalColumns()) . ' column.'));

        /*$types = [ $referencedBeanName ];
        if ($isNullable) {
            $types[] = 'null';
        }
        $getter->getDocBlock()->setTag(new ReturnTag($types));*/

        $getter->setReturnType(($isNullable ? '?' : '') . $this->beanNamespace . '\\' . $referencedBeanName);
        $tdbmFk = ForeignKey::createFromFk($this->foreignKey);

        $getter->setBody('return $this->getRef(' . var_export($tdbmFk->getCacheKey(), true) . ', ' . var_export($tableName, true) . ');');

        if ($this->isGetterProtected()) {
            $getter->setVisibility(AbstractMemberGenerator::VISIBILITY_PROTECTED);
        }

        $setter = new MethodGenerator($setterName);
        $setter->setDocBlock(new DocBlockGenerator('The setter for the ' . $referencedBeanName . ' object bound to this object via the ' . implode(' and ', $this->foreignKey->getUnquotedLocalColumns()) . ' column.'));

        $setter->setParameter(new ParameterGenerator('object', ($isNullable ? '?' : '') . $this->beanNamespace . '\\' . $referencedBeanName));

        $setter->setReturnType('void');

        $setter->setBody('$this->setRef(' . var_export($tdbmFk->getCacheKey(), true) . ', $object, ' . var_export($tableName, true) . ');');

        if ($this->isSetterProtected()) {
            $setter->setVisibility(AbstractMemberGenerator::VISIBILITY_PROTECTED);
        }

        return [$getter, $setter];
    }

    /**
     * Returns the part of code useful when doing json serialization.
     *
     * @return string
     */
    public function getJsonSerializeCode(): string
    {
        if ($this->findAnnotation(Annotation\JsonIgnore::class)) {
            return '';
        }

        if ($this->isGetterProtected()) {
            return '';
        }

        if ($this->findAnnotation(Annotation\JsonCollection::class)) {
            if ($this->findAnnotation(Annotation\JsonInclude::class) ||
                $this->findAnnotation(Annotation\JsonRecursive::class)) {
                return '';
            }
            $isIncluded = false;
            $format = 'jsonSerialize(true)';
        } else {
            $isIncluded = $this->findAnnotation(Annotation\JsonInclude::class) !== null;
            /** @var Annotation\JsonFormat|null $jsonFormat */
            $jsonFormat = $this->findAnnotation(Annotation\JsonFormat::class);
            if ($jsonFormat !== null) {
                $method = $jsonFormat->method ?? 'get' . ucfirst($jsonFormat->property);
                $format = "$method()";
            } else {
                $stopRecursion = $this->findAnnotation(Annotation\JsonRecursive::class) ? '' : 'true';
                $format = "jsonSerialize($stopRecursion)";
            }
        }
        /** @var Annotation\JsonKey|null $jsonKey */
        $jsonKey = $this->findAnnotation(Annotation\JsonKey::class);
        $index = $jsonKey ? $jsonKey->key : $this->namingStrategy->getJsonProperty($this);
        $getter = $this->getGetterName();
        if (!$this->isCompulsory()) {
            $recursiveCode = "\$array['$index'] = (\$object = \$this->$getter()) ? \$object->$format : null;";
            $lazyCode = "\$array['$index'] = (\$object = \$this->$getter()) ? {$this->getLazySerializeCode('$object')} : null;";
        } else {
            $recursiveCode = "\$array['$index'] = \$this->$getter()->$format;";
            $lazyCode = "\$array['$index'] = {$this->getLazySerializeCode("\$this->$getter()")};";
        }

        if ($isIncluded) {
            $code = $recursiveCode;
        } else {
            $code = <<<PHP
if (\$stopRecursion) {
    $lazyCode
} else {
    $recursiveCode
}
PHP;
        }
        return $code;
    }

    public function getLazySerializeCode(string $propertyAccess): string
    {
        $rows = [];
        foreach ($this->getForeignKey()->getUnquotedForeignColumns() as $column) {
            $descriptor = $this->getBeanPropertyDescriptor($column);
            if ($descriptor instanceof ScalarReferencePropertyDescriptor) {
                $descriptor = $descriptor->getReferencedPropertyDescriptor();
            }
            if ($descriptor instanceof ObjectBeanPropertyDescriptor) {
                $rows[] = trim($descriptor->getLazySerializeCode($propertyAccess), '[]');
            } else {
                $indexName = ltrim($descriptor->getVariableName(), '$');
                $columnGetterName = $descriptor->getGetterName();
                $rows[] = "'$indexName' => $propertyAccess->$columnGetterName()";
            }
        }
        return '[' . implode(', ', $rows) . ']';
    }

    private function getBeanPropertyDescriptor(string $column): AbstractBeanPropertyDescriptor
    {
        foreach ($this->foreignBeanDescriptor->getBeanPropertyDescriptors() as $descriptor) {
            if ($descriptor instanceof ScalarBeanPropertyDescriptor && $descriptor->getColumnName() === $column) {
                return $descriptor;
            }
        }
        throw new TDBMException('PropertyDescriptor for `'.$this->table->getName().'`.`' . $column . '` not found in `' . $this->foreignBeanDescriptor->getTable()->getName() . '`');
    }

    /**
     * The code to past in the __clone method.
     * @return null|string
     */
    public function getCloneRule(): ?string
    {
        return null;
    }

    /**
     * Tells if this property is a type-hintable in PHP (resource isn't for example)
     *
     * @return bool
     */
    public function isTypeHintable(): bool
    {
        return true;
    }

    private function isGetterProtected(): bool
    {
        return $this->findAnnotation(Annotation\ProtectedGetter::class) !== null;
    }

    private function isSetterProtected(): bool
    {
        return $this->findAnnotation(Annotation\ProtectedSetter::class) !== null;
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
