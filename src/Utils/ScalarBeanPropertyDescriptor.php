<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use TheCodingMachine\TDBM\TDBMException;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;
use TheCodingMachine\TDBM\Utils\Annotation\Annotations;
use \TheCodingMachine\TDBM\Utils\Annotation;
use Zend\Code\Generator\AbstractMemberGenerator;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;

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
     * @var AnnotationParser
     */
    private $annotationParser;

    /**
     * ScalarBeanPropertyDescriptor constructor.
     * @param Table $table
     * @param Column $column
     * @param NamingStrategyInterface $namingStrategy
     */
    public function __construct(Table $table, Column $column, NamingStrategyInterface $namingStrategy, AnnotationParser $annotationParser)
    {
        parent::__construct($table, $namingStrategy);
        $this->table = $table;
        $this->column = $column;
        $this->annotationParser = $annotationParser;
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
     * Returns the Database type for the property
     *
     * @return Type
     */
    public function getDatabaseType(): Type
    {
        return $this->column->getType();
    }

    /**
     * Returns true if the property is compulsory (and therefore should be fetched in the constructor).
     *
     * @return bool
     */
    public function isCompulsory(): bool
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

    private function getUuidAnnotation(): ?Annotation\UUID
    {
        /** @var Annotation\UUID $annotation */
        $annotation = $this->getAnnotations()->findAnnotation(Annotation\UUID::class);
        return $annotation;
    }

    private function getAutoincrementAnnotation(): ?Annotation\Autoincrement
    {
        /** @var Annotation\Autoincrement $annotation */
        $annotation = $this->getAnnotations()->findAnnotation(Annotation\Autoincrement::class);
        return $annotation;
    }

    private function getAnnotations(): Annotations
    {
        if ($this->annotations === null) {
            $this->annotations = $this->annotationParser->getColumnAnnotations($this->column, $this->table);
        }
        return $this->annotations;
    }

    /**
     * Returns true if the property has a default value (or if the @UUID annotation is set for the column)
     *
     * @return bool
     */
    public function hasDefault(): bool
    {
        // MariaDB 10.3 issue: it returns "NULL" (the string) instead of *null*
        return ($this->column->getDefault() !== null && $this->column->getDefault() !== 'NULL') || $this->hasUuidAnnotation();
    }

    /**
     * Returns the code that assigns a value to its default value.
     *
     * @return string
     */
    public function assignToDefaultCode(): string
    {
        $str = '$this->%s(%s);';

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
                if ($default !== null && in_array(strtoupper($default), ['CURRENT_TIMESTAMP' /* MySQL */, 'NOW()' /* PostgreSQL */, 'SYSDATE' /* Oracle */ , 'CURRENT_TIMESTAMP()' /* MariaDB 10.3 */], true)) {
                    $defaultCode = 'new \DateTimeImmutable()';
                } else {
                    throw new TDBMException('Unable to set default value for date in "'.$this->table->getName().'.'.$this->column->getName().'". Database passed this default value: "'.$default.'"');
                }
            } else {
                $defaultValue = $type->convertToPHPValue($this->column->getDefault(), new MySQL57Platform());
                $defaultCode = var_export($defaultValue, true);
            }
        }

        return sprintf($str, $this->getSetterName(), $defaultCode);
    }

    private function getUuidCode(Annotation\UUID $uuidAnnotation): string
    {
        $comment = $uuidAnnotation->value;
        switch ($comment) {
            case '':
            case 'v1':
                return 'Uuid::uuid1()->toString()';
            case 'v4':
                return 'Uuid::uuid4()->toString()';
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
     * @return MethodGenerator[]
     */
    public function getGetterSetterCode(): array
    {
        $normalizedType = $this->getPhpType();

        $columnGetterName = $this->getGetterName();
        $columnSetterName = $this->getSetterName();
        $variableName = ltrim($this->getSafeVariableName(), '$');

        // A column type can be forced if it is not nullable and not auto-incrementable (for auto-increment columns, we can get "null" as long as the bean is not saved).
        $isNullable = !$this->column->getNotnull() || $this->isAutoincrement();

        $resourceTypeCheck = '';
        if ($normalizedType === 'resource') {
            $checkNullable = '';
            if ($isNullable) {
                $checkNullable = sprintf('$%s !== null && ', $variableName);
            }
            $resourceTypeCheck .= <<<EOF
if (%s!\is_resource($%s)) {
    throw \TheCodingMachine\TDBM\TDBMInvalidArgumentException::badType('resource', $%s, __METHOD__);
}
EOF;
            $resourceTypeCheck = sprintf($resourceTypeCheck, $checkNullable, $variableName, $variableName);
        }

        $types = [ $normalizedType ];
        if ($isNullable) {
            $types[] = 'null';
        }

        $paramType = null;
        if ($this->isTypeHintable()) {
            $paramType = ($isNullable?'?':'').$normalizedType;
        }

        $getter = new MethodGenerator($columnGetterName);
        $getterDocBlock = new DocBlockGenerator(sprintf('The getter for the "%s" column.', $this->column->getName()));
        $getterDocBlock->setTag(new ReturnTag($types))->setWordWrap(false);
        $getter->setDocBlock($getterDocBlock);
        $getter->setReturnType($paramType);

        $getter->setBody(sprintf(
            'return $this->get(%s, %s);',
            var_export($this->column->getName(), true),
            var_export($this->table->getName(), true)
        ));

        if ($this->isGetterProtected()) {
            $getter->setVisibility(AbstractMemberGenerator::VISIBILITY_PROTECTED);
        }

        $setter = new MethodGenerator($columnSetterName);
        $setterDocBlock = new DocBlockGenerator(sprintf('The setter for the "%s" column.', $this->column->getName()));
        $setterDocBlock->setTag(new ParamTag($variableName, $types))->setWordWrap(false);
        $setter->setDocBlock($setterDocBlock);

        $parameter = new ParameterGenerator($variableName, $paramType);
        $setter->setParameter($parameter);
        $setter->setReturnType('void');

        $setter->setBody(sprintf(
            '%s
$this->set(%s, $%s, %s);',
            $resourceTypeCheck,
            var_export($this->column->getName(), true),
            $variableName,
            var_export($this->table->getName(), true)
        ));

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

        if (!$this->canBeSerialized()) {
            return '';
        }

        // Do not export the property is the getter is protected.
        if ($this->isGetterProtected()) {
            return '';
        }

        /** @var Annotation\JsonKey|null $jsonKey */
        $jsonKey = $this->findAnnotation(Annotation\JsonKey::class);
        $index = $jsonKey ? $jsonKey->key : $this->namingStrategy->getJsonProperty($this);
        $getter = $this->getGetterName();
        switch ($this->getPhpType()) {
            case '\\DateTimeImmutable':
                /** @var Annotation\JsonFormat|null $jsonFormat */
                $jsonFormat = $this->findAnnotation(Annotation\JsonFormat::class);
                $format = $jsonFormat ? $jsonFormat->datetime : 'c';
                if ($this->column->getNotnull()) {
                    return "\$array['$index'] = \$this->$getter()->format('$format');";
                } else {
                    return "\$array['$index'] = (\$date = \$this->$getter()) ? \$date->format('$format') : null;";
                }
                // no break
            case 'int':
            case 'float':
                /** @var Annotation\JsonFormat|null $jsonFormat */
                $jsonFormat = $this->findAnnotation(Annotation\JsonFormat::class);
                if ($jsonFormat) {
                    $args = [$jsonFormat->decimals, $jsonFormat->point, $jsonFormat->separator];
                    for ($i = 2; $i >= 0; --$i) {
                        if ($args[$i] === null) {
                            unset($args[$i]);
                        } else {
                            break;
                        }
                    }
                    $args = array_map(function ($v) {
                        return var_export($v, true);
                    }, $args);
                    $args = empty($args) ? '' : ', ' . implode(', ', $args);
                    $unit = $jsonFormat->unit ? ' . ' . var_export($jsonFormat->unit, true) : '';
                    if ($this->column->getNotnull()) {
                        return "\$array['$index'] = number_format(\$this->$getter()$args)$unit;";
                    } else {
                        return "\$array['$index'] = \$this->$getter() !== null ? number_format(\$this->$getter()$args)$unit : null;";
                    }
                }
                // no break
            default:
                return "\$array['$index'] = \$this->$getter();";
        }
    }

    /**
     * Returns the column name.
     *
     * @return string
     */
    public function getColumnName(): string
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
            return sprintf("\$this->%s(%s);\n", $this->getSetterName(), $this->getUuidCode($uuidAnnotation));
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
        return $this->getAnnotations()->findAnnotation($type);
    }
}
