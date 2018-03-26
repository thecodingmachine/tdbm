<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\Type;
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
        // MariaDB 10.3 issue: it returns "NULL" (the string) instead of *null*
        return ($this->column->getDefault() !== null && $this->column->getDefault() !== 'NULL') || $this->hasUuidAnnotation();
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
            $defaultCode = $this->getUuidCode($uuidAnnotation);
        } else {
            $default = $this->column->getDefault();
            $type = $this->column->getType();

            if (in_array($type->getName(), [
                'datetime',
                'datetime_immutable',
                'datetimetz',
                'datetimetz_immutable',
                'date',
                'date_immutable',
                'time',
                'time_immutable',
            ], true)) {
                if (in_array(strtoupper($default), ['CURRENT_TIMESTAMP' /* MySQL */, 'NOW()' /* PostgreSQL */, 'SYSDATE' /* Oracle */ , 'CURRENT_TIMESTAMP()' /* MariaDB 10.3 */], true)) {
                    $defaultCode = 'new \DateTimeImmutable()';
                } else {
                    throw new TDBMException('Unable to set default value for date. Database passed this default value: "'.$default.'"');
                }
            } else {
                $defaultValue = $type->convertToPHPValue($this->column->getDefault(), new MySQL57Platform());
                $defaultCode = var_export($defaultValue, true);
            }
        }

        return sprintf($str, $this->getSetterName(), $defaultCode);
    }

    private function getUuidCode(Annotation $uuidAnnotation): string
    {
        $comment = trim($uuidAnnotation->getAnnotationComment(), '\'"');
        switch ($comment) {
            case '':
            case 'v1':
                return '(string) Uuid::uuid1()';
            case 'v4':
                return '(string) Uuid::uuid4()';
            default:
                throw new TDBMException('@UUID annotation accepts either "v1" or "v4" parameter. Unexpected parameter: ' . $comment);
        }
    }

    /**
     * Returns true if the property is the primary key.
     *
     * @return bool
     */
    public function isPrimaryKey(): bool
    {
        $primaryKey = $this->table->getPrimaryKey();
        if ($primaryKey === null) {
            return false;
        }
        return in_array($this->column->getName(), $primaryKey->getUnquotedColumns());
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

        $resourceTypeCheck = '';
        if ($normalizedType === 'resource') {
            $resourceTypeCheck .= <<<EOF

        if (!\is_resource($%s)) {
            throw \TheCodingMachine\TDBM\TDBMInvalidArgumentException::badType('resource', $%s, __METHOD__);
        }
EOF;
            $resourceTypeCheck = sprintf($resourceTypeCheck, $this->column->getName(), $this->column->getName());
        }

        $getterAndSetterCode = '    /**
     * The getter for the "%s" column.
     *
     * @return %s
     */
    public function %s()%s%s%s
    {
        return $this->get(%s, %s);
    }

    /**
     * The setter for the "%s" column.
     *
     * @param %s $%s
     */
    public function %s(%s%s$%s) : void
    {%s
        $this->set(%s, $%s, %s);
    }

';

        return sprintf(
            $getterAndSetterCode,
            // Getter
            $this->column->getName(),
            $normalizedType.($isNullable ? '|null' : ''),
            $columnGetterName,
            ($this->isTypeHintable() ? ' : ' : ''),
            ($isNullable && $this->isTypeHintable() ? '?' : ''),
            ($this->isTypeHintable() ? $normalizedType: ''),
            var_export($this->column->getName(), true),
            var_export($this->table->getName(), true),
            // Setter
            $this->column->getName(),
            $normalizedType.(($this->column->getNotnull() || !$this->isTypeHintable()) ? '' : '|null'),
            $this->column->getName(),
            $columnSetterName,
            ($this->column->getNotnull() || !$this->isTypeHintable()) ? '' : '?',
            $this->isTypeHintable() ? $normalizedType . ' ' : '',
                //$castTo,
            $this->column->getName(),
            $resourceTypeCheck,
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

        if (!$this->canBeSerialized()) {
            return '';
        }

        if ($normalizedType == '\\DateTimeImmutable') {
            if ($this->column->getNotnull()) {
                return '        $array['.var_export($this->namingStrategy->getJsonProperty($this), true).'] = $this->'.$this->getGetterName()."()->format('c');\n";
            } else {
                return '        $array['.var_export($this->namingStrategy->getJsonProperty($this), true).'] = ($this->'.$this->getGetterName().'() === null) ? null : $this->'.$this->getGetterName()."()->format('c');\n";
            }
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

    /**
     * The code to past in the __clone method.
     * @return null|string
     */
    public function getCloneRule(): ?string
    {
        $uuidAnnotation = $this->getUuidAnnotation();
        if ($uuidAnnotation !== null && $this->isPrimaryKey()) {
            return sprintf("        \$this->%s(%s);\n", $this->getSetterName(), $this->getUuidCode($uuidAnnotation));
        }
        return null;
    }

    /**
     * tells is this type is suitable for Json Serialization
     *
     * @return bool
     */
    public function canBeSerialized() : bool
    {
        $type = $this->column->getType();

        $unserialisableTypes = [
            Type::BLOB,
            Type::BINARY
        ];

        return \in_array($type->getName(), $unserialisableTypes, true) === false;
    }

    /**
     * Tells if this property is a type-hintable in PHP (resource isn't for example)
     *
     * @return bool
     */
    public function isTypeHintable() : bool
    {
        $type = $this->getPhpType();
        $invalidScalarTypes = [
            'resource'
        ];

        return \in_array($type, $invalidScalarTypes, true) === false;
    }
}
