<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use TheCodingMachine\TDBM\TDBMException;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;

/**
 * This class represent a property in a bean that points to another table.
 */
class ObjectBeanPropertyDescriptor extends AbstractBeanPropertyDescriptor
{
    /**
     * @var ForeignKeyConstraint
     */
    private $foreignKey;
    /**
     * @var string
     */
    private $beanNamespace;

    /**
     * ObjectBeanPropertyDescriptor constructor.
     * @param Table $table
     * @param ForeignKeyConstraint $foreignKey
     * @param NamingStrategyInterface $namingStrategy
     */
    public function __construct(Table $table, ForeignKeyConstraint $foreignKey, NamingStrategyInterface $namingStrategy, string $beanNamespace)
    {
        parent::__construct($table, $namingStrategy);
        $this->foreignKey = $foreignKey;
        $this->beanNamespace = $beanNamespace;
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
        return '\\'.$this->beanNamespace.'\\'.$this->getClassName();
    }

    /**
     * Returns true if the property is compulsory (and therefore should be fetched in the constructor).
     *
     * @return bool
     */
    public function isCompulsory(): bool
    {
        // Are all columns nullable?
        $localColumnNames = $this->foreignKey->getUnquotedLocalColumns();

        foreach ($localColumnNames as $name) {
            $column = $this->table->getColumn($name);
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
        $getter->setDocBlock('Returns the '.$referencedBeanName.' object bound to this object via the '.implode(' and ', $this->foreignKey->getUnquotedLocalColumns()).' column.');

        /*$types = [ $referencedBeanName ];
        if ($isNullable) {
            $types[] = 'null';
        }
        $getter->getDocBlock()->setTag(new ReturnTag($types));*/

        $getter->setReturnType(($isNullable?'?':'').$this->beanNamespace.'\\'.$referencedBeanName);

        $getter->setBody('return $this->getRef('.var_export($this->foreignKey->getName(), true).', '.var_export($tableName, true).');');

        $setter = new MethodGenerator($setterName);
        $setter->setDocBlock('The setter for the '.$referencedBeanName.' object bound to this object via the '.implode(' and ', $this->foreignKey->getUnquotedLocalColumns()).' column.');

        $setter->setParameter(new ParameterGenerator('object', ($isNullable?'?':'').$this->beanNamespace.'\\'.$referencedBeanName));

        $setter->setReturnType('void');

        $setter->setBody('$this->setRef('.var_export($this->foreignKey->getName(), true).', $object, '.var_export($tableName, true).');');

        return [$getter, $setter];
    }

    /**
     * Returns the part of code useful when doing json serialization.
     *
     * @return string
     */
    public function getJsonSerializeCode(): string
    {
        if (!$this->isCompulsory()) {
            return 'if (!$stopRecursion) {
    $object = $this->'.$this->getGetterName().'();
    $array['.var_export($this->namingStrategy->getJsonProperty($this), true).'] = $object ? $object->jsonSerialize(true) : null;
}
';
        } else {
            return 'if (!$stopRecursion) {
    $array['.var_export($this->namingStrategy->getJsonProperty($this), true).'] = $this->'.$this->getGetterName().'()->jsonSerialize(true);
}
';
        }
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
    public function isTypeHintable() : bool
    {
        return true;
    }
}
