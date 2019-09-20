<?php
declare(strict_types=1);


namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Index;
use TheCodingMachine\TDBM\ConfigurationInterface;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\MethodGenerator;

/**
 * A base class to ease the usage of CodeGeneratorListenerInterface.
 * It implements all methods with a NOOP.
 */
class BaseCodeGeneratorListener implements CodeGeneratorListenerInterface
{
    public function onBaseBeanGenerated(FileGenerator $fileGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration): ?FileGenerator
    {
        return $fileGenerator;
    }

    public function onBaseBeanConstructorGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    /**
     * Called when a column is turned into a getter/setter.
     *
     * @return array<int, ?MethodGenerator> Returns an array of 2 methods to be generated for this property. You MUST return the getter (first argument) and setter (second argument) as part of these methods (if you want them to appear in the bean). Return null if you want to delete them.
     */
    public function onBaseBeanPropertyGenerated(?MethodGenerator $getter, ?MethodGenerator $setter, AbstractBeanPropertyDescriptor $propertyDescriptor, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): array
    {
        return [$getter, $setter];
    }

    /**
     * Called when a foreign key from another table is turned into a "get many objects" method.
     *
     * @param MethodGenerator $getter
     * @param DirectForeignKeyMethodDescriptor $directForeignKeyMethodDescriptor
     * @param BeanDescriptor $beanDescriptor
     * @param ConfigurationInterface $configuration
     * @param ClassGenerator $classGenerator
     * @return MethodGenerator|null
     */
    public function onBaseBeanOneToManyGenerated(MethodGenerator $getter, DirectForeignKeyMethodDescriptor $directForeignKeyMethodDescriptor, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $getter;
    }

    /**
     * Called when a pivot table is turned into get/has/add/set/remove methods.
     *
     * @return array<int, ?MethodGenerator> Returns an array of methods to be generated for this property. You MUST return the get/add/remove/has/set methods in this order (if you want them to appear in the bean, otherwise return null).
     */
    public function onBaseBeanManyToManyGenerated(?MethodGenerator $getter, ?MethodGenerator $adder, ?MethodGenerator $remover, ?MethodGenerator $hasser, ?MethodGenerator $setter, PivotTableMethodsDescriptor $pivotTableMethodsDescriptor, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): array
    {
        return [$getter, $adder, $remover, $hasser, $setter];
    }

    public function onBaseBeanJsonSerializeGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseBeanCloneGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseDaoGenerated(FileGenerator $fileGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration): ?FileGenerator
    {
        return $fileGenerator;
    }

    public function onBaseDaoConstructorGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseDaoSaveGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseDaoFindAllGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseDaoGetByIdGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseDaoDeleteGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseDaoFindGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseDaoFindFromSqlGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseDaoFindFromRawSqlGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseDaoFindOneGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseDaoFindOneFromSqlGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseDaoSetDefaultSortGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseDaoFindByIndexGenerated(MethodGenerator $methodGenerator, Index $index, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onBaseResultIteratorGenerated(FileGenerator $fileGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration): ?FileGenerator
    {
        return $fileGenerator;
    }

    /**
     * @param BeanDescriptor[] $beanDescriptors
     */
    public function onDaoFactoryGenerated(FileGenerator $fileGenerator, array $beanDescriptors, ConfigurationInterface $configuration): ?FileGenerator
    {
        return $fileGenerator;
    }

    /**
     * @param BeanDescriptor[] $beanDescriptors
     */
    public function onDaoFactoryConstructorGenerated(MethodGenerator $methodGenerator, array $beanDescriptors, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onDaoFactoryGetterGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }

    public function onDaoFactorySetterGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
    {
        return $methodGenerator;
    }
}
