<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Ramsey\Uuid\Uuid;
use TheCodingMachine\TDBM\TDBMException;
use TheCodingMachine\TDBM\Utils\Annotation\Annotation;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;
use TheCodingMachine\TDBM\Utils\Annotation\Annotations;

/**
 * This class represent a property in a bean (a property has a getter, a setter, etc...).
 */
class ScalarBeanPropertyDescriptor extends AbstractBeanPropertyDescriptor
{
    /**
     * @var Column
     */
    private $column;

    /**
     * @var Annotations
     */
    private $annotations;

    /**
     * ScalarBeanPropertyDescriptor constructor.
     * @param Table $table
     * @param Column $column
     * @param NamingStrategyInterface $namingStrategy
     */
    public function __construct(Table $table, Column $column, NamingStrategyInterface $namingStrategy)
    {
        parent::__construct($table, $namingStrategy);
        $this->table = $table;
        $this->column = $column;
    }

    /**
     * Returns the foreign-key the column is part of, if any. null otherwise.
     *
     * @return ForeignKeyConstraint|null
     */
    public function getForeignKey()
    {
        return false;
    }

    /**
     * Returns the param annotation for this property (useful for constructor).
     *
     * @return string
     */
    public function getParamAnnotation()
    {
        $paramType = $this->getPhpType();

        $str = '     * @param %s %s';

        return sprintf($str, $paramType, $this->getVariableName());
    }

    /**
     * Returns the name of the class linked to this property or null if this is not a foreign key.
     *
     * @return null|string
     */
    public function getClassName(): ?string
    {
        return null;
    }

    /**
     * Returns the PHP type for the property (it can be a scalar like int, bool, or class names, like \DateTimeInterface, App\Bean\User....)
     *
     * @return string
     */
    public function getPhpType(): string
    {
        $type = $this->column->getType();
        return TDBMDaoGenerator::dbalTypeToPhpType($type);
    }

    /**
     * Returns true if the property is compulsory (and therefore should be fetched in the constructor).
     *
     * @return bool
     */
    public function isCompulsory()
    {
        return $this->column->getNotnull() && !$this->isAutoincrement() && $this->column->getDefault() === null && !$this->hasUuidAnnotation();
    }

    private function isAutoincrement() : bool
    {
        return $this->column->getAutoincrement() || $this->getAutoincrementAnnotation() !== null;
    }

    private function hasUuidAnnotation(): bool
    {
        return $this->getUuidAnnotation() !== null;
    }

    private function getUuidAnnotation(): ?Annotation
    {
        return $this->getAnnotations()->findAnnotation('UUID');
    }

    private function getAutoincrementAnnotation(): ?Annotation
    {
        return $this->getAnnotations()->findAnnotation('Autoincrement');
    }

    private function getAnnotations(): Annotations
    {
        if ($this->annotations === null) {
            $comment = $this->column->getComment();
            if ($comment === null) {
                return new Annotations([]);
            }
            $parser = new AnnotationParser();
            $this->annotations = $parser->parse($comment);
        }
        return $this->annotations;
    }

    /**
     * Returns true if the property has a default value (or if the @UUID annotation is set for the column)
     *
     * @return bool
     */
    public function hasDefault()
    {
        return $this->column->getDefault() !== null || $this->hasUuidAnnotation();
    }

    /**
     * Returns the code that assigns a value to its default value.
     *
     * @return string
     */
    public function assignToDefaultCode()
    {
        $str = '        $this->%s(%s);';

        $uuidAnnotation = $this->getUuidAnnotation();
        if ($uuidAnnotation !== null) {
            $comment = trim($uuidAnnotation->getAnnotationComment(), '\'"');
            switch ($comment) {
                case '':
                case 'v1':
                    $defaultCode = '(string) Uuid::uuid1()';
                    break;
                case 'v4':
                    $defaultCode = '(string) Uuid::uuid4()';
                    break;
                default:
                    throw new TDBMException('@UUID annotation accepts either "v1" or "v4" parameter. Unexpected parameter: '.$comment);
            }
        } else {
            $default = $this->column->getDefault();

            if (in_array(strtoupper($default), ['CURRENT_TIMESTAMP' /* MySQL */, 'NOW()' /* PostgreSQL */, 'SYSDATE' /* Oracle */ ], true)) {
                $defaultCode = 'new \DateTimeImmutable()';
            } else {
                $defaultCode = var_export($this->column->getDefault(), true);
            }
        }

        return sprintf($str, $this->getSetterName(), $defaultCode);
    }

    /**
     * Returns true if the property is the primary key.
     *
     * @return bool
     */
    public function isPrimaryKey()
    {
        return in_array($this->column->getName(), $this->table->getPrimaryKeyColumns());
    }

    /**
     * Returns the PHP code for getters and setters.
     *
     * @return string
     */
    public function getGetterSetterCode()
    {
        $normalizedType = $this->getPhpType();

        $columnGetterName = $this->getGetterName();
        $columnSetterName = $this->getSetterName();

        // A column type can be forced if it is not nullable and not auto-incrementable (for auto-increment columns, we can get "null" as long as the bean is not saved).
        $isNullable = !$this->column->getNotnull() || $this->isAutoincrement();

        $getterAndSetterCode = '    /**
     * The getter for the "%s" column.
     *
     * @return %s
     */
    public function %s() : %s%s
    {
        return $this->get(%s, %s);
    }

    /**
     * The setter for the "%s" column.
     *
     * @param %s $%s
     */
    public function %s(%s%s $%s) : void
    {
        $this->set(%s, $%s, %s);
    }

';

        return sprintf($getterAndSetterCode,
            // Getter
            $this->column->getName(),
            $normalizedType.($isNullable ? '|null' : ''),
            $columnGetterName,
            ($isNullable ? '?' : ''),
            $normalizedType,
            var_export($this->column->getName(), true),
            var_export($this->table->getName(), true),
            // Setter
            $this->column->getName(),
            $normalizedType.($isNullable ? '|null' : ''),
            $this->column->getName(),
            $columnSetterName,
            $this->column->getNotnull() ? '' : '?',
            $normalizedType,
                //$castTo,
            $this->column->getName(),
            var_export($this->column->getName(), true),
            $this->column->getName(),
            var_export($this->table->getName(), true)
        );
    }

    /**
     * Returns the part of code useful when doing json serialization.
     *
     * @return string
     */
    public function getJsonSerializeCode()
    {
        $normalizedType = $this->getPhpType();

        if ($normalizedType == '\\DateTimeImmutable') {
            return '        $array['.var_export($this->namingStrategy->getJsonProperty($this), true).'] = ($this->'.$this->getGetterName().'() === null) ? null : $this->'.$this->getGetterName()."()->format('c');\n";
        } else {
            return '        $array['.var_export($this->namingStrategy->getJsonProperty($this), true).'] = $this->'.$this->getGetterName()."();\n";
        }
    }

    /**
     * Returns the column name.
     *
     * @return string
     */
    public function getColumnName()
    {
        return $this->column->getName();
    }
}
