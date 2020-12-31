<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Types\Type;
use JsonSerializable;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use PhpParser\Comment\Doc;
use Ramsey\Uuid\Uuid;
use TheCodingMachine\TDBM\AbstractTDBMObject;
use TheCodingMachine\TDBM\AlterableResultIterator;
use TheCodingMachine\TDBM\ConfigurationInterface;
use TheCodingMachine\TDBM\InnerResultIterator;
use TheCodingMachine\TDBM\ResultIterator;
use TheCodingMachine\TDBM\SafeFunctions;
use TheCodingMachine\TDBM\Schema\ForeignKey;
use TheCodingMachine\TDBM\Schema\ForeignKeys;
use TheCodingMachine\TDBM\TDBMException;
use TheCodingMachine\TDBM\TDBMSchemaAnalyzer;
use TheCodingMachine\TDBM\TDBMService;
use TheCodingMachine\TDBM\Utils\Annotation\AbstractTraitAnnotation;
use TheCodingMachine\TDBM\Utils\Annotation\AddInterfaceOnDao;
use TheCodingMachine\TDBM\Utils\Annotation\AddTrait;
use TheCodingMachine\TDBM\Utils\Annotation\AddTraitOnDao;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;
use TheCodingMachine\TDBM\Utils\Annotation\AddInterface;
use Zend\Code\Generator\AbstractMemberGenerator;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\DocBlock\Tag\ThrowsTag;
use Zend\Code\Generator\DocBlock\Tag\VarTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Code\Generator\PropertyGenerator;
use function implode;
use function var_export;

/**
 * This class represents a bean.
 */
class BeanDescriptor implements BeanDescriptorInterface
{
    /**
     * @var Table
     */
    private $table;

    /**
     * @var SchemaAnalyzer
     */
    private $schemaAnalyzer;

    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var AbstractBeanPropertyDescriptor[]
     */
    private $beanPropertyDescriptors = [];

    /**
     * @var TDBMSchemaAnalyzer
     */
    private $tdbmSchemaAnalyzer;

    /**
     * @var NamingStrategyInterface
     */
    private $namingStrategy;
    /**
     * @var string
     */
    private $beanNamespace;
    /**
     * @var string
     */
    private $generatedBeanNamespace;
    /**
     * @var AnnotationParser
     */
    private $annotationParser;
    /**
     * @var string
     */
    private $daoNamespace;
    /**
     * @var string
     */
    private $generatedDaoNamespace;
    /**
     * @var string
     */
    private $resultIteratorNamespace;
    /**
     * @var string
     */
    private $generatedResultIteratorNamespace;
    /**
     * @var CodeGeneratorListenerInterface
     */
    private $codeGeneratorListener;
    /**
     * @var ConfigurationInterface
     */
    private $configuration;
    /**
     * @var BeanRegistry
     */
    private $registry;
    /**
     * @var MethodDescriptorInterface[][]
     */
    private $descriptorsByMethodName = [];
    /**
     * @var DirectForeignKeyMethodDescriptor[]|null
     */
    private $directForeignKeysDescriptors = null;
    /**
     * @var PivotTableMethodsDescriptor[]|null
     */
    private $pivotTableDescriptors = null;

    public function __construct(
        Table $table,
        string $beanNamespace,
        string $generatedBeanNamespace,
        string $daoNamespace,
        string $generatedDaoNamespace,
        string $resultIteratorNamespace,
        string $generatedResultIteratorNamespace,
        SchemaAnalyzer $schemaAnalyzer,
        Schema $schema,
        TDBMSchemaAnalyzer $tdbmSchemaAnalyzer,
        NamingStrategyInterface $namingStrategy,
        AnnotationParser $annotationParser,
        CodeGeneratorListenerInterface $codeGeneratorListener,
        ConfigurationInterface $configuration,
        BeanRegistry $registry
    ) {
        $this->table = $table;
        $this->beanNamespace = $beanNamespace;
        $this->generatedBeanNamespace = $generatedBeanNamespace;
        $this->daoNamespace = $daoNamespace;
        $this->generatedDaoNamespace = $generatedDaoNamespace;
        $this->resultIteratorNamespace = $resultIteratorNamespace;
        $this->generatedResultIteratorNamespace = $generatedResultIteratorNamespace;
        $this->schemaAnalyzer = $schemaAnalyzer;
        $this->schema = $schema;
        $this->tdbmSchemaAnalyzer = $tdbmSchemaAnalyzer;
        $this->namingStrategy = $namingStrategy;
        $this->annotationParser = $annotationParser;
        $this->codeGeneratorListener = $codeGeneratorListener;
        $this->configuration = $configuration;
        $this->registry = $registry;
    }

    public function initBeanPropertyDescriptors(): void
    {
        $this->beanPropertyDescriptors = $this->getProperties($this->table);

        //init the list of method names with regular properties names
        foreach ($this->beanPropertyDescriptors as $beanPropertyDescriptor) {
            $this->checkForDuplicate($beanPropertyDescriptor);
        }
    }

    /**
     * Returns the foreign-key the column is part of, if any. null otherwise.
     *
     * @param Table  $table
     * @param Column $column
     *
     * @return ForeignKeyConstraint|null
     */
    private function isPartOfForeignKey(Table $table, Column $column) : ?ForeignKeyConstraint
    {
        $localColumnName = $column->getName();
        foreach ($table->getForeignKeys() as $foreignKey) {
            foreach ($foreignKey->getUnquotedLocalColumns() as $columnName) {
                if ($columnName === $localColumnName) {
                    return $foreignKey;
                }
            }
        }

        return null;
    }

    /**
     * @return AbstractBeanPropertyDescriptor[]
     */
    public function getBeanPropertyDescriptors(): array
    {
        return $this->beanPropertyDescriptors;
    }

    /**
     * Returns the list of columns that are not nullable and not autogenerated for a given table and its parent.
     *
     * @return AbstractBeanPropertyDescriptor[]
     */
    public function getConstructorProperties(): array
    {
        $constructorProperties = array_filter($this->beanPropertyDescriptors, static function (AbstractBeanPropertyDescriptor $property) {
            return !$property instanceof InheritanceReferencePropertyDescriptor && $property->isCompulsory() && !$property->isReadOnly();
        });

        return $constructorProperties;
    }

    /**
     * Returns the list of columns that have default values for a given table.
     *
     * @return AbstractBeanPropertyDescriptor[]
     */
    public function getPropertiesWithDefault(): array
    {
        $properties = $this->getPropertiesForTable($this->table);
        $defaultProperties = array_filter($properties, function (AbstractBeanPropertyDescriptor $property) {
            return $property->hasDefault();
        });

        return $defaultProperties;
    }

    /**
     * Returns the list of properties exposed as getters and setters in this class.
     *
     * @return AbstractBeanPropertyDescriptor[]
     */
    public function getExposedProperties(): array
    {
        $exposedProperties = array_filter($this->beanPropertyDescriptors, function (AbstractBeanPropertyDescriptor $property) {
            return !$property instanceof InheritanceReferencePropertyDescriptor && $property->getTable()->getName() === $this->table->getName();
        });

        return $exposedProperties;
    }

    /**
     * Returns the list of properties for this table (including parent tables).
     *
     * @param Table $table
     *
     * @return AbstractBeanPropertyDescriptor[]
     */
    private function getProperties(Table $table): array
    {
        // Security check: a table MUST have a primary key
        TDBMDaoGenerator::getPrimaryKeyColumnsOrFail($table);

        $parentRelationship = $this->schemaAnalyzer->getParentRelationship($table->getName());
        if ($parentRelationship) {
            $parentTable = $this->schema->getTable($parentRelationship->getForeignTableName());
            $properties = $this->getProperties($parentTable);
            // we merge properties by overriding property names.
            $localProperties = $this->getPropertiesForTable($table);
            foreach ($localProperties as $name => $property) {
                // We do not override properties if this is a primary key!
                if (!$property instanceof InheritanceReferencePropertyDescriptor && $property->isPrimaryKey()) {
                    continue;
                }
                $properties[$name] = $property;
            }
        } else {
            $properties = $this->getPropertiesForTable($table);
        }

        return $properties;
    }

    /**
     * Returns the list of properties for this table (ignoring parent tables).
     *
     * @param Table $table
     *
     * @return AbstractBeanPropertyDescriptor[]
     */
    private function getPropertiesForTable(Table $table): array
    {
        $parentRelationship = $this->schemaAnalyzer->getParentRelationship($table->getName());
        if ($parentRelationship) {
            $ignoreColumns = $parentRelationship->getUnquotedForeignColumns();
        } else {
            $ignoreColumns = [];
        }

        $beanPropertyDescriptors = [];
        foreach ($table->getColumns() as $column) {
            if (in_array($column->getName(), $ignoreColumns, true)) {
                continue;
            }

            $fk = $this->isPartOfForeignKey($table, $column);
            if ($fk !== null) {
                // Check that previously added descriptors are not added on same FK (can happen with multi key FK).
                foreach ($beanPropertyDescriptors as $beanDescriptor) {
                    if ($beanDescriptor instanceof ObjectBeanPropertyDescriptor && $beanDescriptor->getForeignKey() === $fk) {
                        continue 2;
                    }
                }
                $propertyDescriptor = new ObjectBeanPropertyDescriptor($table, $fk, $this->namingStrategy, $this->beanNamespace, $this->annotationParser, $this->registry->getBeanForTableName($fk->getForeignTableName()));
                // Check that this property is not an inheritance relationship
                $parentRelationship = $this->schemaAnalyzer->getParentRelationship($table->getName());
                if ($parentRelationship !== null && $parentRelationship->getName() === $fk->getName()) {
                    $beanPropertyDescriptors[] = new InheritanceReferencePropertyDescriptor(
                        $table,
                        $column,
                        $this->namingStrategy,
                        $this->annotationParser,
                        $propertyDescriptor
                    );
                } else {
                    $beanPropertyDescriptors[] = $propertyDescriptor;
                }
            } else {
                $beanPropertyDescriptors[] = new ScalarBeanPropertyDescriptor($table, $column, $this->namingStrategy, $this->annotationParser);
            }
        }

        // Now, let's get the name of all properties and let's check there is no duplicate.
        /* @var $names AbstractBeanPropertyDescriptor[] */
        $names = [];
        foreach ($beanPropertyDescriptors as $beanDescriptor) {
            $name = $beanDescriptor->getGetterName();
            if (isset($names[$name])) {
                $names[$name]->useAlternativeName();
                $beanDescriptor->useAlternativeName();
            } else {
                $names[$name] = $beanDescriptor;
            }
        }

        // Final check (throw exceptions if problem arises)
        $names = [];
        foreach ($beanPropertyDescriptors as $beanDescriptor) {
            $name = $beanDescriptor->getGetterName();
            if (isset($names[$name])) {
                throw new TDBMException('Unsolvable name conflict while generating method name "' . $name . '"');
            } else {
                $names[$name] = $beanDescriptor;
            }
        }

        // Last step, let's rebuild the list with a map:
        $beanPropertyDescriptorsMap = [];
        foreach ($beanPropertyDescriptors as $beanDescriptor) {
            $beanPropertyDescriptorsMap[$beanDescriptor->getVariableName()] = $beanDescriptor;
        }

        return $beanPropertyDescriptorsMap;
    }

    private function generateBeanConstructor() : MethodGenerator
    {
        $constructorProperties = $this->getConstructorProperties();

        $constructor = new MethodGenerator('__construct', [], MethodGenerator::FLAG_PUBLIC);
        $constructorDocBlock = new DocBlockGenerator('The constructor takes all compulsory arguments.');
        $constructorDocBlock->setWordWrap(false);
        $constructor->setDocBlock($constructorDocBlock);

        $assigns = [];
        $parentConstructorArguments = [];

        foreach ($constructorProperties as $property) {
            $parameter = new ParameterGenerator(ltrim($property->getSafeVariableName(), '$'));
            if ($property->isTypeHintable()) {
                $parameter->setType($property->getPhpType());
            }
            $constructor->setParameter($parameter);

            $constructorDocBlock->setTag($property->getParamAnnotation());

            if ($property->getTable()->getName() === $this->table->getName()) {
                $assigns[] = $property->getConstructorAssignCode()."\n";
            } else {
                $parentConstructorArguments[] = $property->getSafeVariableName();
            }
        }

        $parentConstructorCode = sprintf("parent::__construct(%s);\n", implode(', ', $parentConstructorArguments));

        foreach ($this->getPropertiesWithDefault() as $property) {
            $assigns[] = $property->assignToDefaultCode()."\n";
        }

        $body = $parentConstructorCode . implode('', $assigns);

        $constructor->setBody($body);

        return $constructor;
    }

    /**
     * Returns the descriptors of one-to-many relationships (the foreign keys pointing on this beans)
     *
     * @return DirectForeignKeyMethodDescriptor[]
     */
    private function getDirectForeignKeysDescriptors(): array
    {
        if ($this->directForeignKeysDescriptors !== null) {
            return $this->directForeignKeysDescriptors;
        }
        $fks = $this->tdbmSchemaAnalyzer->getIncomingForeignKeys($this->table->getName());

        $descriptors = [];

        foreach ($fks as $fk) {
            $desc = new DirectForeignKeyMethodDescriptor($fk, $this->table, $this->namingStrategy, $this->annotationParser, $this->beanNamespace);
            $this->checkForDuplicate($desc);
            $descriptors[] = $desc;
        }

        $this->directForeignKeysDescriptors = $descriptors;
        return $this->directForeignKeysDescriptors;
    }

    /**
     * @return PivotTableMethodsDescriptor[]
     */
    private function getPivotTableDescriptors(): array
    {
        if ($this->pivotTableDescriptors !== null) {
            return $this->pivotTableDescriptors;
        }
        $descs = [];
        foreach ($this->schemaAnalyzer->detectJunctionTables(true) as $table) {
            // There are exactly 2 FKs since this is a pivot table.
            $fks = array_values($table->getForeignKeys());

            if ($fks[0]->getForeignTableName() === $this->table->getName()) {
                list($localFk, $remoteFk) = $fks;
                $desc = new PivotTableMethodsDescriptor($table, $localFk, $remoteFk, $this->namingStrategy, $this->beanNamespace, $this->annotationParser);
                $this->checkForDuplicate($desc);
                $descs[] = $desc;
            }
            if ($fks[1]->getForeignTableName() === $this->table->getName()) {
                list($remoteFk, $localFk) = $fks;
                $desc = new PivotTableMethodsDescriptor($table, $localFk, $remoteFk, $this->namingStrategy, $this->beanNamespace, $this->annotationParser);
                $this->checkForDuplicate($desc);
                $descs[] = $desc;
            }
        }

        $this->pivotTableDescriptors = $descs;
        return $this->pivotTableDescriptors;
    }

    /**
     * Check the method name isn't already used and flag the associated descriptors to use their alternative names if it is the case
     */
    private function checkForDuplicate(MethodDescriptorInterface $descriptor): void
    {
        $name = $descriptor->getName();
        if (!isset($this->descriptorsByMethodName[$name])) {
            $this->descriptorsByMethodName[$name] = [];
        }
        $this->descriptorsByMethodName[$name][] = $descriptor;
        if (count($this->descriptorsByMethodName[$name]) > 1) {
            foreach ($this->descriptorsByMethodName[$name] as $duplicateDescriptor) {
                $duplicateDescriptor->useAlternativeName();
            }
        }
    }

    /**
     * Returns the list of method descriptors (and applies the alternative name if needed).
     *
     * @return RelationshipMethodDescriptorInterface[]
     */
    public function getMethodDescriptors(): array
    {
        $directForeignKeyDescriptors = $this->getDirectForeignKeysDescriptors();
        $pivotTableDescriptors = $this->getPivotTableDescriptors();

        return array_merge($directForeignKeyDescriptors, $pivotTableDescriptors);
    }

    public function generateJsonSerialize(): MethodGenerator
    {
        $tableName = $this->table->getName();
        $parentFk = $this->schemaAnalyzer->getParentRelationship($tableName);

        $method = new MethodGenerator('jsonSerialize');
        $method->setDocBlock(new DocBlockGenerator(
            'Serializes the object for JSON encoding.',
            null,
            [
                new ParamTag('$stopRecursion', ['bool'], 'Parameter used internally by TDBM to stop embedded objects from embedding other objects.'),
                new ReturnTag(['array'])
            ]
        ));
        $method->setParameter(new ParameterGenerator('stopRecursion', 'bool', false));

        if ($parentFk !== null) {
            $body = '$array = parent::jsonSerialize($stopRecursion);';
        } else {
            $body = '$array = [];';
        }

        foreach ($this->getExposedProperties() as $beanPropertyDescriptor) {
            $propertyCode = $beanPropertyDescriptor->getJsonSerializeCode();
            if (!empty($propertyCode)) {
                $body .= PHP_EOL . $propertyCode;
            }
        }

        // Many2many relationships
        foreach ($this->getMethodDescriptors() as $methodDescriptor) {
            $methodCode = $methodDescriptor->getJsonSerializeCode();
            if (!empty($methodCode)) {
                $body .= PHP_EOL . $methodCode;
            }
        }

        $body .= PHP_EOL . 'return $array;';

        $method->setBody($body);

        return $method;
    }

    /**
     * Returns as an array the class we need to extend from and the list of use statements.
     *
     * @param ForeignKeyConstraint|null $parentFk
     * @return string[]
     */
    private function generateExtendsAndUseStatements(ForeignKeyConstraint $parentFk = null): array
    {
        $classes = [];
        if ($parentFk !== null) {
            $extends = $this->namingStrategy->getBeanClassName($parentFk->getForeignTableName());
            $classes[] = $extends;
        }

        foreach ($this->getBeanPropertyDescriptors() as $beanPropertyDescriptor) {
            $className = $beanPropertyDescriptor->getClassName();
            if (null !== $className) {
                $classes[] = $className;
            }
        }

        foreach ($this->getMethodDescriptors() as $descriptor) {
            $classes = array_merge($classes, $descriptor->getUsedClasses());
        }

        $classes = array_unique($classes);

        return $classes;
    }

    /**
     * Returns the representation of the PHP bean file with all getters and setters.
     *
     * @return ?FileGenerator
     */
    public function generatePhpCode(): ?FileGenerator
    {
        $file = new FileGenerator();
        $class = new ClassGenerator();
        $class->setAbstract(true);
        $file->setClass($class);
        $file->setNamespace($this->generatedBeanNamespace);

        $tableName = $this->table->getName();
        $baseClassName = $this->namingStrategy->getBaseBeanClassName($tableName);
        $className = $this->namingStrategy->getBeanClassName($tableName);
        $parentFk = $this->schemaAnalyzer->getParentRelationship($this->table->getName());

        $classes = $this->generateExtendsAndUseStatements($parentFk);

        foreach ($classes as $useClass) {
            $file->setUse($this->beanNamespace.'\\'.$useClass);
        }

        /*$uses = array_map(function ($className) {
            return 'use '.$this->beanNamespace.'\\'.$className.";\n";
        }, $classes);
        $use = implode('', $uses);*/

        $extends = $this->getExtendedBeanClassName();
        if ($extends === null) {
            $class->setExtendedClass(AbstractTDBMObject::class);
            $file->setUse(AbstractTDBMObject::class);
        } else {
            $class->setExtendedClass($extends);
        }

        $file->setUse(ResultIterator::class);
        $file->setUse(AlterableResultIterator::class);
        $file->setUse(Uuid::class);
        $file->setUse(JsonSerializable::class);
        $file->setUse(ForeignKeys::class);

        $class->setName($baseClassName);

        $file->setDocBlock(new DocBlockGenerator(
            'This file has been automatically generated by TDBM.',
            <<<EOF
DO NOT edit this file, as it might be overwritten.
If you need to perform changes, edit the $className class instead!
EOF
        ));

        $class->setDocBlock(new DocBlockGenerator("The $baseClassName class maps the '$tableName' table in database."));

        /** @var AddInterface[] $addInterfaceAnnotations */
        $addInterfaceAnnotations = $this->annotationParser->getTableAnnotations($this->table)->findAnnotations(AddInterface::class);

        $interfaces = [ JsonSerializable::class ];
        foreach ($addInterfaceAnnotations as $annotation) {
            $interfaces[] = $annotation->getName();
        }

        $class->setImplementedInterfaces($interfaces);

        $this->registerTraits($class, AddTrait::class);

        $method = $this->generateBeanConstructor();
        $method = $this->codeGeneratorListener->onBaseBeanConstructorGenerated($method, $this, $this->configuration, $class);
        if ($method) {
            $class->addMethodFromGenerator($this->generateBeanConstructor());
        }

        $fks = [];
        foreach ($this->getExposedProperties() as $property) {
            if ($property instanceof ObjectBeanPropertyDescriptor) {
                $fks[] = $property->getForeignKey();
            }
            [$getter, $setter] = $property->getGetterSetterCode();
            [$getter, $setter] = $this->codeGeneratorListener->onBaseBeanPropertyGenerated($getter, $setter, $property, $this, $this->configuration, $class);
            if ($getter !== null) {
                $class->addMethodFromGenerator($getter);
            }
            if ($setter !== null) {
                $class->addMethodFromGenerator($setter);
            }
        }

        $pivotTableMethodsDescriptors = [];
        foreach ($this->getMethodDescriptors() as $methodDescriptor) {
            if ($methodDescriptor instanceof DirectForeignKeyMethodDescriptor) {
                [$method] = $methodDescriptor->getCode();
                $method = $this->codeGeneratorListener->onBaseBeanOneToManyGenerated($method, $methodDescriptor, $this, $this->configuration, $class);
                if ($method) {
                    $class->addMethodFromGenerator($method);
                }
            } elseif ($methodDescriptor instanceof PivotTableMethodsDescriptor) {
                $pivotTableMethodsDescriptors[] = $methodDescriptor;
                [ $getter, $adder, $remover, $has, $setter ] = $methodDescriptor->getCode();
                $methods = $this->codeGeneratorListener->onBaseBeanManyToManyGenerated($getter, $adder, $remover, $has, $setter, $methodDescriptor, $this, $this->configuration, $class);
                foreach ($methods as $method) {
                    if ($method) {
                        $class->addMethodFromGenerator($method);
                    }
                }
            } else {
                throw new \RuntimeException('Unexpected instance'); // @codeCoverageIgnore
            }
        }

        $manyToManyRelationshipCode = $this->generateGetManyToManyRelationshipDescriptorCode($pivotTableMethodsDescriptors);
        if ($manyToManyRelationshipCode !== null) {
            $class->addMethodFromGenerator($manyToManyRelationshipCode);
        }
        $manyToManyRelationshipKeysCode = $this->generateGetManyToManyRelationshipDescriptorKeysCode($pivotTableMethodsDescriptors);
        if ($manyToManyRelationshipKeysCode !== null) {
            $class->addMethodFromGenerator($manyToManyRelationshipKeysCode);
        }

        $foreignKeysProperty = new PropertyGenerator('foreignKeys');
        $foreignKeysProperty->setStatic(true);
        $foreignKeysProperty->setVisibility(AbstractMemberGenerator::VISIBILITY_PRIVATE);
        $foreignKeysProperty->setDocBlock(new DocBlockGenerator(null, null, [new VarTag(null, ['\\'.ForeignKeys::class])]));
        $class->addPropertyFromGenerator($foreignKeysProperty);

        $method = $this->generateGetForeignKeys($fks);
        $class->addMethodFromGenerator($method);

        $method = $this->generateJsonSerialize();
        $method = $this->codeGeneratorListener->onBaseBeanJsonSerializeGenerated($method, $this, $this->configuration, $class);
        if ($method !== null) {
            $class->addMethodFromGenerator($method);
        }

        $class->addMethodFromGenerator($this->generateGetUsedTablesCode());
        $onDeleteCode = $this->generateOnDeleteCode();
        if ($onDeleteCode) {
            $class->addMethodFromGenerator($onDeleteCode);
        }
        $cloneCode = $this->generateCloneCode($pivotTableMethodsDescriptors);
        $cloneCode = $this->codeGeneratorListener->onBaseBeanCloneGenerated($cloneCode, $this, $this->configuration, $class);
        if ($cloneCode) {
            $class->addMethodFromGenerator($cloneCode);
        }

        $file = $this->codeGeneratorListener->onBaseBeanGenerated($file, $this, $this->configuration);

        return $file;
    }

    private function registerTraits(ClassGenerator $class, string $annotationClass): void
    {
        /** @var AbstractTraitAnnotation[] $addTraitAnnotations */
        $addTraitAnnotations = $this->annotationParser->getTableAnnotations($this->table)->findAnnotations($annotationClass);

        foreach ($addTraitAnnotations as $annotation) {
            $class->addTrait($annotation->getName());
        }

        foreach ($addTraitAnnotations as $annotation) {
            foreach ($annotation->getInsteadOf() as $method => $replacedTrait) {
                $class->addTraitOverride($method, $replacedTrait);
            }
            foreach ($annotation->getAs() as $method => $replacedMethod) {
                $class->addTraitAlias($method, $replacedMethod);
            }
        }
    }

    /**
     * Writes the representation of the PHP DAO file.
     *
     * @return ?FileGenerator
     */
    public function generateDaoPhpCode(): ?FileGenerator
    {
        $file = new FileGenerator();
        $class = new ClassGenerator();
        $class->setAbstract(true);
        $file->setClass($class);
        $file->setNamespace($this->generatedDaoNamespace);

        $tableName = $this->table->getName();

        $primaryKeyColumns = TDBMDaoGenerator::getPrimaryKeyColumnsOrFail($this->table);

        list($defaultSort, $defaultSortDirection) = $this->getDefaultSortColumnFromAnnotation($this->table);

        $className = $this->namingStrategy->getDaoClassName($tableName);
        $baseClassName = $this->namingStrategy->getBaseDaoClassName($tableName);
        $beanClassWithoutNameSpace = $this->namingStrategy->getBeanClassName($tableName);
        $beanClassName = $this->beanNamespace.'\\'.$beanClassWithoutNameSpace;

        $findByDaoCodeMethods = $this->generateFindByDaoCode($this->beanNamespace, $beanClassWithoutNameSpace, $class);

        $usedBeans[] = $beanClassName;
        // Let's suppress duplicates in used beans (if any)
        $usedBeans = array_flip(array_flip($usedBeans));
        foreach ($usedBeans as $usedBean) {
            $class->addUse($usedBean);
        }

        $file->setDocBlock(new DocBlockGenerator(
            <<<EOF
This file has been automatically generated by TDBM.
DO NOT edit this file, as it might be overwritten.
If you need to perform changes, edit the $className class instead!
EOF
        ));

        $file->setNamespace($this->generatedDaoNamespace);

        $class->addUse(TDBMService::class);
        $class->addUse(ResultIterator::class);
        $class->addUse(TDBMException::class);

        $class->setName($baseClassName);

        $class->setDocBlock(new DocBlockGenerator("The $baseClassName class will maintain the persistence of $beanClassWithoutNameSpace class into the $tableName table."));

        /** @var AddInterfaceOnDao[] $addInterfaceOnDaoAnnotations */
        $addInterfaceOnDaoAnnotations = $this->annotationParser->getTableAnnotations($this->table)->findAnnotations(AddInterfaceOnDao::class);

        $interfaces = [];
        foreach ($addInterfaceOnDaoAnnotations as $annotation) {
            $interfaces[] = $annotation->getName();
        }

        $class->setImplementedInterfaces($interfaces);

        $this->registerTraits($class, AddTraitOnDao::class);

        $tdbmServiceProperty = new PropertyGenerator('tdbmService');
        $tdbmServiceProperty->setDocBlock(new DocBlockGenerator(null, null, [new VarTag(null, ['\\'.TDBMService::class])]));
        $class->addPropertyFromGenerator($tdbmServiceProperty);

        $defaultSortProperty = new PropertyGenerator('defaultSort', $defaultSort);
        $defaultSortProperty->setDocBlock(new DocBlockGenerator('The default sort column.', null, [new VarTag(null, ['string', 'null'])]));
        $class->addPropertyFromGenerator($defaultSortProperty);

        $defaultSortPropertyDirection = new PropertyGenerator('defaultDirection', $defaultSort && $defaultSortDirection ? $defaultSortDirection : 'asc');
        $defaultSortPropertyDirection->setDocBlock(new DocBlockGenerator('The default sort direction.', null, [new VarTag(null, ['string'])]));
        $class->addPropertyFromGenerator($defaultSortPropertyDirection);

        $constructorMethod = new MethodGenerator(
            '__construct',
            [ new ParameterGenerator('tdbmService', TDBMService::class) ],
            MethodGenerator::FLAG_PUBLIC,
            '$this->tdbmService = $tdbmService;',
            'Sets the TDBM service used by this DAO.'
        );
        $constructorMethod = $this->codeGeneratorListener->onBaseDaoConstructorGenerated($constructorMethod, $this, $this->configuration, $class);
        if ($constructorMethod !== null) {
            $class->addMethodFromGenerator($constructorMethod);
        }

        $saveMethod = new MethodGenerator(
            'save',
            [ new ParameterGenerator('obj', $beanClassName) ],
            MethodGenerator::FLAG_PUBLIC,
            '$this->tdbmService->save($obj);',
            (new DocBlockGenerator(
                "Persist the $beanClassWithoutNameSpace instance.",
                null,
                [
                    new ParamTag('obj', [$beanClassWithoutNameSpace], 'The bean to save.')
                ]
            ))->setWordWrap(false)
        );
        $saveMethod->setReturnType('void');

        $saveMethod = $this->codeGeneratorListener->onBaseDaoSaveGenerated($saveMethod, $this, $this->configuration, $class);
        if ($saveMethod !== null) {
            $class->addMethodFromGenerator($saveMethod);
        }

        $findAllBody = <<<EOF
if (\$this->defaultSort) {
    \$orderBy = '$tableName.'.\$this->defaultSort.' '.\$this->defaultDirection;
} else {
    \$orderBy = null;
}
return \$this->tdbmService->findObjects('$tableName', null, [], \$orderBy, [], null, null, \\$this->resultIteratorNamespace\\{$this->getResultIteratorClassName()}::class);
EOF;

        $findAllMethod = new MethodGenerator(
            'findAll',
            [],
            MethodGenerator::FLAG_PUBLIC,
            $findAllBody,
            (new DocBlockGenerator("Get all $beanClassWithoutNameSpace records."))->setWordWrap(false)
        );
        $findAllMethod->setReturnType($this->resultIteratorNamespace . '\\' . $this->getResultIteratorClassName());
        $findAllMethod = $this->codeGeneratorListener->onBaseDaoFindAllGenerated($findAllMethod, $this, $this->configuration, $class);
        if ($findAllMethod !== null) {
            $class->addMethodFromGenerator($findAllMethod);
        }

        if (count($primaryKeyColumns) > 0) {
            $lazyLoadingParameterName = 'lazyLoading';
            $parameters = [];
            $parametersTag = [];
            $primaryKeyFilter = [];

            foreach ($primaryKeyColumns as $primaryKeyColumn) {
                if ($primaryKeyColumn === $lazyLoadingParameterName) {
                    throw new TDBMException('Primary Column name `' . $lazyLoadingParameterName . '` is not allowed.');
                }
                $phpType = TDBMDaoGenerator::dbalTypeToPhpType($this->table->getColumn($primaryKeyColumn)->getType());
                $parameters[] = new ParameterGenerator($primaryKeyColumn, $phpType);
                $parametersTag[] = new ParamTag($primaryKeyColumn, [$phpType]);
                $primaryKeyFilter[] = "'$primaryKeyColumn' => \$$primaryKeyColumn";
            }
            $parameters[] = new ParameterGenerator($lazyLoadingParameterName, 'bool', false);
            $parametersTag[] = new ParamTag($lazyLoadingParameterName, ['bool'], 'If set to true, the object will not be loaded right away. Instead, it will be loaded when you first try to access a method of the object.');
            $parametersTag[] = new ReturnTag(['\\'.$beanClassName]);
            $parametersTag[] = new ThrowsTag('\\'.TDBMException::class);

            $getByIdMethod = new MethodGenerator(
                'getById',
                $parameters,
                MethodGenerator::FLAG_PUBLIC,
                "return \$this->tdbmService->findObjectByPk('$tableName', [" . implode(', ', $primaryKeyFilter) . "], [], \$$lazyLoadingParameterName);",
                (new DocBlockGenerator(
                    "Get $beanClassWithoutNameSpace specified by its ID (its primary key).",
                    'If the primary key does not exist, an exception is thrown.',
                    $parametersTag
                ))->setWordWrap(false)
            );
            $getByIdMethod->setReturnType($beanClassName);
            $getByIdMethod = $this->codeGeneratorListener->onBaseDaoGetByIdGenerated($getByIdMethod, $this, $this->configuration, $class);
            if ($getByIdMethod) {
                $class->addMethodFromGenerator($getByIdMethod);
            }
        }

        $deleteMethodBody = <<<EOF
if (\$cascade === true) {
    \$this->tdbmService->deleteCascade(\$obj);
} else {
    \$this->tdbmService->delete(\$obj);
}
EOF;


        $deleteMethod = new MethodGenerator(
            'delete',
            [
                new ParameterGenerator('obj', $beanClassName),
                new ParameterGenerator('cascade', 'bool', false)
            ],
            MethodGenerator::FLAG_PUBLIC,
            $deleteMethodBody,
            (new DocBlockGenerator(
                "Get all $beanClassWithoutNameSpace records.",
                null,
                [
                    new ParamTag('obj', ['\\'.$beanClassName], 'The object to delete'),
                    new ParamTag('cascade', ['bool'], 'If true, it will delete all objects linked to $obj'),
                ]
            ))->setWordWrap(false)
        );
        $deleteMethod->setReturnType('void');
        $deleteMethod = $this->codeGeneratorListener->onBaseDaoDeleteGenerated($deleteMethod, $this, $this->configuration, $class);
        if ($deleteMethod !== null) {
            $class->addMethodFromGenerator($deleteMethod);
        }

        $findMethodBody = <<<EOF
if (\$this->defaultSort && \$orderBy == null) {
    \$orderBy = '$tableName.'.\$this->defaultSort.' '.\$this->defaultDirection;
}
return \$this->tdbmService->findObjects('$tableName', \$filter, \$parameters, \$orderBy, \$additionalTablesFetch, \$mode, null, \\$this->resultIteratorNamespace\\{$this->getResultIteratorClassName()}::class);
EOF;


        $findMethod = new MethodGenerator(
            'find',
            [
                (new ParameterGenerator('filter'))->setDefaultValue(null),
                new ParameterGenerator('parameters', 'array', []),
                (new ParameterGenerator('orderBy'))->setDefaultValue(null),
                new ParameterGenerator('additionalTablesFetch', 'array', []),
                (new ParameterGenerator('mode', '?int'))->setDefaultValue(null),
            ],
            MethodGenerator::FLAG_PROTECTED,
            $findMethodBody,
            (new DocBlockGenerator(
                "Get all $beanClassWithoutNameSpace records.",
                null,
                [
                    new ParamTag('filter', ['mixed'], 'The filter bag (see TDBMService::findObjects for complete description)'),
                    new ParamTag('parameters', ['mixed[]'], 'The parameters associated with the filter'),
                    new ParamTag('orderBy', ['mixed'], 'The order string'),
                    new ParamTag('additionalTablesFetch', ['string[]'], 'A list of additional tables to fetch (for performance improvement)'),
                    new ParamTag('mode', ['int', 'null'], 'Either TDBMService::MODE_ARRAY or TDBMService::MODE_CURSOR (for large datasets). Defaults to TDBMService::MODE_ARRAY.')
                ]
            ))->setWordWrap(false)
        );
        $findMethod->setReturnType($this->resultIteratorNamespace . '\\' . $this->getResultIteratorClassName());
        $findMethod = $this->codeGeneratorListener->onBaseDaoFindGenerated($findMethod, $this, $this->configuration, $class);
        if ($findMethod !== null) {
            $class->addMethodFromGenerator($findMethod);
        }

        $findFromSqlMethodBody = <<<EOF
if (\$this->defaultSort && \$orderBy == null) {
    \$orderBy = '$tableName.'.\$this->defaultSort.' '.\$this->defaultDirection;
}
return \$this->tdbmService->findObjectsFromSql('$tableName', \$from, \$filter, \$parameters, \$orderBy, \$mode, null, \\$this->resultIteratorNamespace\\{$this->getResultIteratorClassName()}::class);
EOF;

        $findFromSqlMethod = new MethodGenerator(
            'findFromSql',
            [
                new ParameterGenerator('from', 'string'),
                (new ParameterGenerator('filter'))->setDefaultValue(null),
                new ParameterGenerator('parameters', 'array', []),
                (new ParameterGenerator('orderBy'))->setDefaultValue(null),
                new ParameterGenerator('additionalTablesFetch', 'array', []),
                (new ParameterGenerator('mode', '?int'))->setDefaultValue(null),
            ],
            MethodGenerator::FLAG_PROTECTED,
            $findFromSqlMethodBody,
            (new DocBlockGenerator(
                "Get a list of $beanClassWithoutNameSpace specified by its filters.",
                "Unlike the `find` method that guesses the FROM part of the statement, here you can pass the \$from part.

You should not put an alias on the main table name. So your \$from variable should look like:

   \"$tableName JOIN ... ON ...\"",
                [
                    new ParamTag('from', ['string'], 'The sql from statement'),
                    new ParamTag('filter', ['mixed'], 'The filter bag (see TDBMService::findObjects for complete description)'),
                    new ParamTag('parameters', ['mixed[]'], 'The parameters associated with the filter'),
                    new ParamTag('orderBy', ['mixed'], 'The order string'),
                    new ParamTag('additionalTablesFetch', ['string[]'], 'A list of additional tables to fetch (for performance improvement)'),
                    new ParamTag('mode', ['int', 'null'], 'Either TDBMService::MODE_ARRAY or TDBMService::MODE_CURSOR (for large datasets). Defaults to TDBMService::MODE_ARRAY.')
                ]
            ))->setWordWrap(false)
        );
        $findFromSqlMethod->setReturnType($this->resultIteratorNamespace . '\\' . $this->getResultIteratorClassName());
        $findFromSqlMethod = $this->codeGeneratorListener->onBaseDaoFindFromSqlGenerated($findFromSqlMethod, $this, $this->configuration, $class);
        if ($findFromSqlMethod !== null) {
            $class->addMethodFromGenerator($findFromSqlMethod);
        }

        $findFromRawSqlMethodBody = <<<EOF
return \$this->tdbmService->findObjectsFromRawSql('$tableName', \$sql, \$parameters, \$mode, null, \$countSql, \\$this->resultIteratorNamespace\\{$this->getResultIteratorClassName()}::class);
EOF;

        $findFromRawSqlMethod = new MethodGenerator(
            'findFromRawSql',
            [
                new ParameterGenerator('sql', 'string'),
                new ParameterGenerator('parameters', 'array', []),
                (new ParameterGenerator('countSql', '?string'))->setDefaultValue(null),
                (new ParameterGenerator('mode', '?int'))->setDefaultValue(null),
            ],
            MethodGenerator::FLAG_PROTECTED,
            $findFromRawSqlMethodBody,
            (new DocBlockGenerator(
                "Get a list of $beanClassWithoutNameSpace from a SQL query.",
                "Unlike the `find` and `findFromSql` methods, here you can pass the whole \$sql query.

You should not put an alias on the main table name, and select its columns using `*`. So the SELECT part of you \$sql should look like:

   \"SELECT $tableName .* FROM ...\"",
                [
                    new ParamTag('sql', ['string'], 'The sql query'),
                    new ParamTag('parameters', ['mixed[]'], 'The parameters associated with the query'),
                    new ParamTag('countSql', ['string', 'null'], 'The sql query that provides total count of rows (automatically computed if not provided)'),
                    new ParamTag('mode', ['int', 'null'], 'Either TDBMService::MODE_ARRAY or TDBMService::MODE_CURSOR (for large datasets). Defaults to TDBMService::MODE_ARRAY.')
                ]
            ))->setWordWrap(false)
        );
        $findFromRawSqlMethod->setReturnType($this->resultIteratorNamespace . '\\' . $this->getResultIteratorClassName());
        $findFromRawSqlMethod = $this->codeGeneratorListener->onBaseDaoFindFromRawSqlGenerated($findFromRawSqlMethod, $this, $this->configuration, $class);
        if ($findFromRawSqlMethod !== null) {
            $class->addMethodFromGenerator($findFromRawSqlMethod);
        }

        $findOneMethodBody = <<<EOF
return \$this->tdbmService->findObject('$tableName', \$filter, \$parameters, \$additionalTablesFetch);
EOF;


        $findOneMethod = new MethodGenerator(
            'findOne',
            [
                (new ParameterGenerator('filter'))->setDefaultValue(null),
                new ParameterGenerator('parameters', 'array', []),
                new ParameterGenerator('additionalTablesFetch', 'array', []),
            ],
            MethodGenerator::FLAG_PROTECTED,
            $findOneMethodBody,
            (new DocBlockGenerator(
                "Get a single $beanClassWithoutNameSpace specified by its filters.",
                null,
                [
                    new ParamTag('filter', ['mixed'], 'The filter bag (see TDBMService::findObjects for complete description)'),
                    new ParamTag('parameters', ['mixed[]'], 'The parameters associated with the filter'),
                    new ParamTag('additionalTablesFetch', ['string[]'], 'A list of additional tables to fetch (for performance improvement)'),
                    new ReturnTag(['\\'.$beanClassName, 'null'])
                ]
            ))->setWordWrap(false)
        );
        $findOneMethod->setReturnType("?$beanClassName");
        $findOneMethod = $this->codeGeneratorListener->onBaseDaoFindOneGenerated($findOneMethod, $this, $this->configuration, $class);
        if ($findOneMethod !== null) {
            $class->addMethodFromGenerator($findOneMethod);
        }

        $findOneFromSqlMethodBody = <<<EOF
return \$this->tdbmService->findObjectFromSql('$tableName', \$from, \$filter, \$parameters);
EOF;

        $findOneFromSqlMethod = new MethodGenerator(
            'findOneFromSql',
            [
                new ParameterGenerator('from', 'string'),
                (new ParameterGenerator('filter'))->setDefaultValue(null),
                new ParameterGenerator('parameters', 'array', []),
            ],
            MethodGenerator::FLAG_PROTECTED,
            $findOneFromSqlMethodBody,
            (new DocBlockGenerator(
                "Get a single $beanClassWithoutNameSpace specified by its filters.",
                "Unlike the `findOne` method that guesses the FROM part of the statement, here you can pass the \$from part.

You should not put an alias on the main table name. So your \$from variable should look like:

    \"$tableName JOIN ... ON ...\"",
                [
                    new ParamTag('from', ['string'], 'The sql from statement'),
                    new ParamTag('filter', ['mixed'], 'The filter bag (see TDBMService::findObjects for complete description)'),
                    new ParamTag('parameters', ['mixed[]'], 'The parameters associated with the filter'),
                    new ReturnTag(['\\'.$beanClassName, 'null'])
                ]
            ))->setWordWrap(false)
        );
        $findOneFromSqlMethod->setReturnType("?$beanClassName");
        $findOneFromSqlMethod = $this->codeGeneratorListener->onBaseDaoFindOneFromSqlGenerated($findOneFromSqlMethod, $this, $this->configuration, $class);
        if ($findOneFromSqlMethod !== null) {
            $class->addMethodFromGenerator($findOneFromSqlMethod);
        }


        $setDefaultSortMethod = new MethodGenerator(
            'setDefaultSort',
            [
                new ParameterGenerator('defaultSort', 'string'),
            ],
            MethodGenerator::FLAG_PUBLIC,
            '$this->defaultSort = $defaultSort;',
            new DocBlockGenerator(
                "Sets the default column for default sorting.",
                null,
                [
                    new ParamTag('defaultSort', ['string']),
                ]
            )
        );
        $setDefaultSortMethod->setReturnType('void');
        $setDefaultSortMethod = $this->codeGeneratorListener->onBaseDaoSetDefaultSortGenerated($setDefaultSortMethod, $this, $this->configuration, $class);
        if ($setDefaultSortMethod !== null) {
            $class->addMethodFromGenerator($setDefaultSortMethod);
        }

        foreach ($findByDaoCodeMethods as $method) {
            $class->addMethodFromGenerator($method);
        }

        $file = $this->codeGeneratorListener->onBaseDaoGenerated($file, $this, $this->configuration);

        return $file;
    }

    /**
     * Writes the representation of the PHP ResultIterator file.
     */
    public function generateResultIteratorPhpCode(): ?FileGenerator
    {
        $file = new FileGenerator();
        $class = new ClassGenerator();
        $class->setAbstract(true);
        $file->setClass($class);
        $file->setNamespace($this->generatedResultIteratorNamespace);

        $tableName = $this->table->getName();

        $className = $this->namingStrategy->getResultIteratorClassName($tableName);
        $classFullName = $this->resultIteratorNamespace.'\\'.$className;
        $baseClassName = $this->namingStrategy->getBaseResultIteratorClassName($tableName);
        $beanClassName = $this->namingStrategy->getBeanClassName($tableName);
        $beanClassFullName = $this->beanNamespace.'\\'.$beanClassName;

        $file->setDocBlock(new DocBlockGenerator(
            <<<EOF
This file has been automatically generated by TDBM.
DO NOT edit this file, as it might be overwritten.
If you need to perform changes, edit the $className class instead!
EOF
        ));

        $uses = [ResultIterator::class, $classFullName, $beanClassFullName];
        sort($uses);
        foreach ($uses as $use) {
            $class->addUse($use);
        }

        $class->setName($baseClassName);
        $class->setExtendedClass(ResultIterator::class);

        $class->setDocBlock((new DocBlockGenerator(
            "The $baseClassName class will iterate over results of $beanClassName class.",
            null,
            [
                new Tag\MethodTag('first', [$beanClassName]),
                new Tag\MethodTag('offsetGet', [$beanClassName]),
                new Tag\MethodTag('getIterator', [$beanClassName . '[]']),
                new Tag\MethodTag('toArray', [$beanClassName . '[]']),
                new Tag\MethodTag('withOrder', [$className]),
                new Tag\MethodTag('withParameters', [$className]),
            ]
        ))->setWordWrap(false));

        $file = $this->codeGeneratorListener->onBaseResultIteratorGenerated($file, $this, $this->configuration);

        return $file;
    }

    /**
     * Tries to find a @defaultSort annotation in one of the columns.
     *
     * @param Table $table
     *
     * @return mixed[] First item: column name, Second item: column order (asc/desc)
     */
    private function getDefaultSortColumnFromAnnotation(Table $table): array
    {
        $defaultSort = null;
        $defaultSortDirection = null;
        foreach ($table->getColumns() as $column) {
            $comments = $column->getComment();
            $matches = [];
            if ($comments !== null && preg_match('/@defaultSort(\((desc|asc)\))*/', $comments, $matches) != 0) {
                $defaultSort = $column->getName();
                if (count($matches) === 3) {
                    $defaultSortDirection = $matches[2];
                } else {
                    $defaultSortDirection = 'ASC';
                }
            }
        }

        return [$defaultSort, $defaultSortDirection];
    }

    /**
     * @param string $beanNamespace
     * @param string $beanClassName
     *
     * @return MethodGenerator[]
     */
    private function generateFindByDaoCode(string $beanNamespace, string $beanClassName, ClassGenerator $class): array
    {
        $methods = [];
        foreach ($this->removeDuplicateIndexes($this->table->getIndexes()) as $index) {
            if (!$index->isPrimary()) {
                $method = $this->generateFindByDaoCodeForIndex($index, $beanNamespace, $beanClassName);

                if ($method !== null) {
                    $method = $this->codeGeneratorListener->onBaseDaoFindByIndexGenerated($method, $index, $this, $this->configuration, $class);
                    if ($method !== null) {
                        $methods[] = $method;
                    }
                }
            }
        }
        usort($methods, static function (MethodGenerator $methodA, MethodGenerator $methodB) {
            return $methodA->getName() <=> $methodB->getName();
        });

        return $methods;
    }

    /**
     * Remove identical indexes (indexes on same columns)
     *
     * @param Index[] $indexes
     * @return Index[]
     */
    private function removeDuplicateIndexes(array $indexes): array
    {
        $indexesByKey = [];
        foreach ($indexes as $index) {
            $key = implode('__`__', $index->getUnquotedColumns());
            // Unique Index have precedence over non unique one
            if (!isset($indexesByKey[$key]) || $index->isUnique()) {
                $indexesByKey[$key] = $index;
            }
        }

        return array_values($indexesByKey);
    }

    /**
     * @param Index  $index
     * @param string $beanNamespace
     * @param string $beanClassName
     *
     * @return MethodGenerator|null
     */
    private function generateFindByDaoCodeForIndex(Index $index, string $beanNamespace, string $beanClassName): ?MethodGenerator
    {
        $columns = $index->getColumns();
        $usedBeans = [];

        /**
         * The list of elements building this index (expressed as columns or foreign keys)
         * @var AbstractBeanPropertyDescriptor[]
         */
        $elements = [];

        foreach ($columns as $column) {
            $fk = $this->isPartOfForeignKey($this->table, $this->table->getColumn($column));
            if ($fk !== null) {
                if (!isset($elements[$fk->getName()])) {
                    $elements[$fk->getName()] = new ObjectBeanPropertyDescriptor($this->table, $fk, $this->namingStrategy, $this->beanNamespace, $this->annotationParser, $this->registry->getBeanForTableName($fk->getForeignTableName()));
                }
            } else {
                $elements[] = new ScalarBeanPropertyDescriptor($this->table, $this->table->getColumn($column), $this->namingStrategy, $this->annotationParser);
            }
        }
        $elements = array_values($elements);

        // If the index is actually only a foreign key, let's bypass it entirely.
        if (count($elements) === 1 && $elements[0] instanceof ObjectBeanPropertyDescriptor) {
            return null;
        }

        $parameters = [];
        //$functionParameters = [];
        $first = true;
        /** @var AbstractBeanPropertyDescriptor $element */
        foreach ($elements as $element) {
            $parameter = new ParameterGenerator(ltrim($element->getSafeVariableName(), '$'));
            if (!$first && !($element->isCompulsory() && $index->isUnique())) {
                $parameterType = '?';
            //$functionParameter = '?';
            } else {
                $parameterType = '';
                //$functionParameter = '';
            }
            $parameterType .= $element->getPhpType();
            $parameter->setType($parameterType);
            if (!$first && !($element->isCompulsory() && $index->isUnique())) {
                $parameter->setDefaultValue(null);
            }
            //$functionParameter .= $element->getPhpType();
            $elementClassName = $element->getClassName();
            if ($elementClassName) {
                $usedBeans[] = $beanNamespace.'\\'.$elementClassName;
            }
            //$functionParameter .= ' '.$element->getVariableName();
            if ($first) {
                $first = false;
            } /*else {
                $functionParameter .= ' = null';
            }*/
            //$functionParameters[] = $functionParameter;
            $parameters[] = $parameter;
        }

        //$functionParametersString = implode(', ', $functionParameters);

        $count = 0;

        $params = [];
        $filterArrayCode = '';
        $commentArguments = [];
        $first = true;
        foreach ($elements as $element) {
            $params[] = $element->getParamAnnotation();
            if ($element instanceof ScalarBeanPropertyDescriptor) {
                $typeName = $element->getDatabaseType()->getName();
                if ($typeName === Type::DATETIME_IMMUTABLE) {
                    $filterArrayCode .= sprintf(
                        "            %s => \$this->tdbmService->getConnection()->convertToDatabaseValue(%s, %s),\n",
                        var_export($element->getColumnName(), true),
                        $element->getSafeVariableName(),
                        var_export($typeName, true)
                    );
                } else {
                    $filterArrayCode .= '            '.var_export($element->getColumnName(), true).' => '.$element->getSafeVariableName().",\n";
                }
            } elseif ($element instanceof ObjectBeanPropertyDescriptor) {
                $foreignKey = $element->getForeignKey();
                $columns = SafeFunctions::arrayCombine($foreignKey->getUnquotedLocalColumns(), $foreignKey->getUnquotedForeignColumns());
                ++$count;
                $foreignTable = $this->schema->getTable($foreignKey->getForeignTableName());
                foreach ($columns as $localColumn => $foreignColumn) {
                    // TODO: a foreign key could point to another foreign key. In this case, there is no getter for the pointed column. We don't support this case.
                    $targetedElement = new ScalarBeanPropertyDescriptor($foreignTable, $foreignTable->getColumn($foreignColumn), $this->namingStrategy, $this->annotationParser);
                    if ($first || $element->isCompulsory() && $index->isUnique()) {
                        // First parameter for index is not nullable
                        $filterArrayCode .= '            '.var_export($localColumn, true).' => '.$element->getSafeVariableName().'->'.$targetedElement->getGetterName()."(),\n";
                    } else {
                        // Other parameters for index is not nullable
                        $filterArrayCode .= '            '.var_export($localColumn, true).' => ('.$element->getSafeVariableName().' !== null) ? '.$element->getSafeVariableName().'->'.$targetedElement->getGetterName()."() : null,\n";
                    }
                }
            }
            $commentArguments[] = substr($element->getSafeVariableName(), 1);
            if ($first) {
                $first = false;
            }
        }

        //$paramsString = implode("\n", $params);


        $methodName = $this->namingStrategy->getFindByIndexMethodName($index, $elements);

        $method = new MethodGenerator($methodName);

        if ($index->isUnique()) {
            $parameters[] = new ParameterGenerator('additionalTablesFetch', 'array', []);
            $params[] = new ParamTag('additionalTablesFetch', [ 'string[]' ], 'A list of additional tables to fetch (for performance improvement)');
            $params[] = new ReturnTag([ '\\'.$beanNamespace.'\\'.$beanClassName, 'null' ]);
            $method->setReturnType('?\\'.$beanNamespace.'\\'.$beanClassName);

            $docBlock = new DocBlockGenerator("Get a $beanClassName filtered by ".implode(', ', $commentArguments). '.', null, $params);
            $docBlock->setWordWrap(false);

            $body = "\$filter = [
".$filterArrayCode."        ];
return \$this->findOne(\$filter, [], \$additionalTablesFetch);
";
        } else {
            $parameters[] = (new ParameterGenerator('orderBy'))->setDefaultValue(null);
            $params[] = new ParamTag('orderBy', [ 'mixed' ], 'The order string');
            $parameters[] = new ParameterGenerator('additionalTablesFetch', 'array', []);
            $params[] = new ParamTag('additionalTablesFetch', [ 'string[]' ], 'A list of additional tables to fetch (for performance improvement)');
            $parameters[] = (new ParameterGenerator('mode', '?int'))->setDefaultValue(null);
            $params[] = new ParamTag('mode', [ 'int', 'null' ], 'Either TDBMService::MODE_ARRAY or TDBMService::MODE_CURSOR (for large datasets). Defaults to TDBMService::MODE_ARRAY.');
            $method->setReturnType($this->resultIteratorNamespace . '\\' . $this->getResultIteratorClassName());

            $docBlock = new DocBlockGenerator("Get a list of $beanClassName filtered by ".implode(', ', $commentArguments).".", null, $params);
            $docBlock->setWordWrap(false);

            $body = "\$filter = [
".$filterArrayCode."        ];
return \$this->find(\$filter, [], \$orderBy, \$additionalTablesFetch, \$mode);
";
        }

        $method->setParameters($parameters);
        $method->setDocBlock($docBlock);
        $method->setBody($body);

        return $method;
    }

    /**
     * Generates the code for the getUsedTable protected method.
     *
     * @return MethodGenerator
     */
    private function generateGetUsedTablesCode(): MethodGenerator
    {
        $hasParentRelationship = $this->schemaAnalyzer->getParentRelationship($this->table->getName()) !== null;
        if ($hasParentRelationship) {
            $code = sprintf('$tables = parent::getUsedTables();
$tables[] = %s;

return $tables;', var_export($this->table->getName(), true));
        } else {
            $code = sprintf('        return [ %s ];', var_export($this->table->getName(), true));
        }

        $method = new MethodGenerator('getUsedTables');
        $method->setDocBlock(new DocBlockGenerator(
            'Returns an array of used tables by this bean (from parent to child relationship).',
            null,
            [new ReturnTag(['string[]'])]
        ));
        $method->setReturnType('array');
        $method->setBody($code);

        return $method;
    }

    private function generateOnDeleteCode(): ?MethodGenerator
    {
        $code = '';
        $relationships = $this->getPropertiesForTable($this->table);
        foreach ($relationships as $relationship) {
            if ($relationship instanceof ObjectBeanPropertyDescriptor) {
                $tdbmFk = ForeignKey::createFromFk($relationship->getForeignKey());
                $code .= '$this->setRef('.var_export($tdbmFk->getCacheKey(), true).', null, '.var_export($this->table->getName(), true).");\n";
            }
        }

        if (!$code) {
            return null;
        }

        $method = new MethodGenerator('onDelete');
        $method->setDocBlock(new DocBlockGenerator('Method called when the bean is removed from database.'));
        $method->setReturnType('void');
        $method->setBody('parent::onDelete();
'.$code);

        return $method;
    }

    /**
     * @param PivotTableMethodsDescriptor[] $pivotTableMethodsDescriptors
     * @return MethodGenerator
     */
    private function generateGetManyToManyRelationshipDescriptorCode(array $pivotTableMethodsDescriptors): ?MethodGenerator
    {
        if (empty($pivotTableMethodsDescriptors)) {
            return null;
        }

        $method = new MethodGenerator('_getManyToManyRelationshipDescriptor');
        $method->setVisibility(AbstractMemberGenerator::VISIBILITY_PUBLIC);
        $method->setDocBlock(new DocBlockGenerator(
            'Get the paths used for many to many relationships methods.',
            null,
            [new GenericTag('internal')]
        ));
        $method->setReturnType(ManyToManyRelationshipPathDescriptor::class);

        $parameter = new ParameterGenerator('pathKey');
        $parameter->setType('string');
        $method->setParameter($parameter);

        $code = 'switch ($pathKey) {'."\n";
        foreach ($pivotTableMethodsDescriptors as $pivotTableMethodsDescriptor) {
            $code .= '    case '.var_export($pivotTableMethodsDescriptor->getManyToManyRelationshipKey(), true).":\n";
            $code .= '        return '.$pivotTableMethodsDescriptor->getManyToManyRelationshipInstantiationCode().";\n";
        }
        $code .= "    default:\n";
        $code .= "        return parent::_getManyToManyRelationshipDescriptor(\$pathKey);\n";
        $code .= "}\n";

        $method->setBody($code);

        return $method;
    }

    /**
     * @param PivotTableMethodsDescriptor[] $pivotTableMethodsDescriptors
     * @return MethodGenerator
     */
    private function generateGetManyToManyRelationshipDescriptorKeysCode(array $pivotTableMethodsDescriptors): ?MethodGenerator
    {
        if (empty($pivotTableMethodsDescriptors)) {
            return null;
        }

        $method = new MethodGenerator('_getManyToManyRelationshipDescriptorKeys');
        $method->setVisibility(AbstractMemberGenerator::VISIBILITY_PUBLIC);
        $method->setReturnType('array');
        $method->setDocBlock(new DocBlockGenerator(
            'Returns the list of keys supported for many to many relationships',
            null,
            [new GenericTag('internal'), new ReturnTag('string[]')]
        ));

        $keys = [];
        foreach ($pivotTableMethodsDescriptors as $pivotTableMethodsDescriptor) {
            $keys[] = var_export($pivotTableMethodsDescriptor->getManyToManyRelationshipKey(), true);
        }

        $code = 'return array_merge(parent::_getManyToManyRelationshipDescriptorKeys(), ['.implode(', ', $keys).']);';

        $method->setBody($code);

        return $method;
    }

    /**
     * @param PivotTableMethodsDescriptor[] $pivotTableMethodsDescriptors
     * @return MethodGenerator
     */
    private function generateCloneCode(array $pivotTableMethodsDescriptors): MethodGenerator
    {
        $precode = '';
        $postcode = '';

        foreach ($this->beanPropertyDescriptors as $beanPropertyDescriptor) {
            $postcode .= $beanPropertyDescriptor->getCloneRule();
        }

        //cloning many to many relationships
        foreach ($pivotTableMethodsDescriptors as $beanMethodDescriptor) {
            $precode .= $beanMethodDescriptor->getCloneRule()."\n";
        }

        $method = new MethodGenerator('__clone');
        $method->setBody($precode."parent::__clone();\n".$postcode);

        return $method;
    }

    /**
     * Returns the bean class name (without the namespace).
     *
     * @return string
     */
    public function getBeanClassName() : string
    {
        return $this->namingStrategy->getBeanClassName($this->table->getName());
    }

    /**
     * Returns the base bean class name (without the namespace).
     *
     * @return string
     */
    public function getBaseBeanClassName() : string
    {
        return $this->namingStrategy->getBaseBeanClassName($this->table->getName());
    }

    /**
     * Returns the DAO class name (without the namespace).
     *
     * @return string
     */
    public function getDaoClassName() : string
    {
        return $this->namingStrategy->getDaoClassName($this->table->getName());
    }

    /**
     * Returns the base DAO class name (without the namespace).
     *
     * @return string
     */
    public function getBaseDaoClassName() : string
    {
        return $this->namingStrategy->getBaseDaoClassName($this->table->getName());
    }

    /**
     * Returns the ResultIterator class name (without the namespace).
     *
     * @return string
     */
    public function getResultIteratorClassName() : string
    {
        return $this->namingStrategy->getResultIteratorClassName($this->table->getName());
    }

    /**
     * Returns the base ResultIterator class name (without the namespace).
     *
     * @return string
     */
    public function getBaseResultIteratorClassName() : string
    {
        return $this->namingStrategy->getBaseResultIteratorClassName($this->table->getName());
    }

    /**
     * Returns the table used to build this bean.
     *
     * @return Table
     */
    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * Returns the extended bean class name (without the namespace), or null if the bean is not extended.
     *
     * @return string
     */
    public function getExtendedBeanClassName(): ?string
    {
        $parentFk = $this->schemaAnalyzer->getParentRelationship($this->table->getName());
        if ($parentFk !== null) {
            return $this->namingStrategy->getBeanClassName($parentFk->getForeignTableName());
        } else {
            return null;
        }
    }

    /**
     * @return string
     */
    public function getBeanNamespace(): string
    {
        return $this->beanNamespace;
    }

    /**
     * @return string
     */
    public function getGeneratedBeanNamespace(): string
    {
        return $this->generatedBeanNamespace;
    }

    /**
     * @param ForeignKeyConstraint[] $fks
     */
    private function generateGetForeignKeys(array $fks): MethodGenerator
    {
        $fkArray = [];

        foreach ($fks as $fk) {
            $tdbmFk = ForeignKey::createFromFk($fk);
            $fkArray[$tdbmFk->getCacheKey()] = [
                ForeignKey::FOREIGN_TABLE => $fk->getForeignTableName(),
                ForeignKey::LOCAL_COLUMNS => $fk->getUnquotedLocalColumns(),
                ForeignKey::FOREIGN_COLUMNS => $fk->getUnquotedForeignColumns(),
            ];
        }

        ksort($fkArray);
        foreach ($fkArray as $tableFks) {
            ksort($tableFks);
        }

        $code = <<<EOF
if (\$tableName === %s) {
    if (self::\$foreignKeys === null) {
        self::\$foreignKeys = new ForeignKeys(%s);
    }
    return self::\$foreignKeys;
}
return parent::getForeignKeys(\$tableName);
EOF;
        $code = sprintf($code, var_export($this->getTable()->getName(), true), $this->psr2VarExport($fkArray, '        '));

        $method = new MethodGenerator('getForeignKeys');
        $method->setVisibility(AbstractMemberGenerator::VISIBILITY_PROTECTED);
        $method->setStatic(true);
        $method->setDocBlock(new DocBlockGenerator('Internal method used to retrieve the list of foreign keys attached to this bean.'));
        $method->setReturnType(ForeignKeys::class);

        $parameter = new ParameterGenerator('tableName');
        $parameter->setType('string');
        $method->setParameter($parameter);


        $method->setBody($code);

        return $method;
    }

    /**
     * @param mixed $var
     * @param string $indent
     * @return string
     */
    private function psr2VarExport($var, string $indent=''): string
    {
        if (is_array($var)) {
            $indexed = array_keys($var) === range(0, count($var) - 1);
            $r = [];
            foreach ($var as $key => $value) {
                $r[] = "$indent    "
                    . ($indexed ? '' : $this->psr2VarExport($key) . ' => ')
                    . $this->psr2VarExport($value, "$indent    ");
            }
            return "[\n" . implode(",\n", $r) . "\n" . $indent . ']';
        }
        return var_export($var, true);
    }
}
