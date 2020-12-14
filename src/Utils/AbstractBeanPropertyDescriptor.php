<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Table;
use TheCodingMachine\TDBM\Utils\Annotation\ReadOnly;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\MethodGenerator;

/**
 * This class represent a property in a bean (a property has a getter, a setter, etc...).
 */
abstract class AbstractBeanPropertyDescriptor implements MethodDescriptorInterface
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
     * @var NamingStrategyInterface
     */
    protected $namingStrategy;

    /**
     * @param Table $table
     * @param NamingStrategyInterface $namingStrategy
     */
    public function __construct(Table $table, NamingStrategyInterface $namingStrategy)
    {
        $this->table = $table;
        $this->namingStrategy = $namingStrategy;
    }

    /**
     * Use the more complex name in case of conflict.
     */
    public function useAlternativeName(): void
    {
        $this->alternativeName = true;
    }

    /**
     * Returns the name of the class linked to this property or null if this is not a foreign key.
     *
     * @return null|string
     */
    abstract public function getClassName(): ?string;

    /**
     * Returns the PHP type for the property (it can be a scalar like int, bool, or class names, like \DateTimeInterface, App\Bean\User....)
     *
     * @return string
     */
    abstract public function getPhpType(): string;

    /**
     * Returns the param annotation for this property (useful for constructor).
     *
     * @return ParamTag
     */
    public function getParamAnnotation(): ParamTag
    {
        return new ParamTag($this->getSafeVariableName(), [ $this->getPhpType() ]);
    }

    public function getVariableName(): string
    {
        return $this->namingStrategy->getVariableName($this);
    }

    public function getSafeVariableName(): string
    {
        return '$' . StringUtils::getValidVariableName(ltrim($this->getVariableName(), '$'));
    }

    public function getSetterName(): string
    {
        return $this->namingStrategy->getSetterName($this);
    }

    /**
     * Alias of the method getGetterName(). Used to validate MethodDescriptorInterface
     */
    public function getName(): string
    {
        return $this->getGetterName();
    }

    public function getGetterName(): string
    {
        return $this->namingStrategy->getGetterName($this);
    }

    /**
     * Returns the PHP code used in the ben constructor for this property.
     *
     * @return string
     */
    public function getConstructorAssignCode(): string
    {
        $str = '$this->%s(%s);';

        return sprintf($str, $this->getSetterName(), $this->getSafeVariableName());
    }

    /**
     * Returns true if the property is compulsory (and therefore should be fetched in the constructor).
     *
     * @return bool
     */
    abstract public function isCompulsory(): bool;

    /**
     * Returns true if the property has a default value.
     *
     * @return bool
     */
    abstract public function hasDefault(): bool;

    /**
     * Returns the code that assigns a value to its default value.
     *
     * @return string
     */
    abstract public function assignToDefaultCode(): string;

    /**
     * Returns true if the property is the primary key.
     *
     * @return bool
     */
    abstract public function isPrimaryKey(): bool;

    /**
     * @return Table
     */
    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * Returns the PHP code for getters and setters.
     *
     * @return (MethodGenerator|null)[]
     */
    abstract public function getGetterSetterCode(): array;

    /**
     * Returns the part of code useful when doing json serialization.
     *
     * @return string
     */
    abstract public function getJsonSerializeCode(): string;

    /**
     * @return bool
     */
    public function isAlternativeName(): bool
    {
        return $this->alternativeName;
    }

    /**
     * The code to past in the __clone method.
     * @return null|string
     */
    abstract public function getCloneRule(): ?string;

    /**
     * Tells if this property is a type-hintable in PHP (resource isn't for example)
     *
     * @return bool
     */
    abstract public function isTypeHintable() : bool;

    /**
     * Returns true if the property is tagged with the "ReadOnly" annotation.
     * ReadOnly annotations should be used on generated/computed database columns.
     *
     * @return bool
     */
    abstract public function isReadOnly(): bool;
}
