<?php
declare(strict_types=1);


namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Index;
use TheCodingMachine\TDBM\ConfigurationInterface;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\MethodGenerator;

/**
 * Listens to events when code is generated for DAOs and beans.
 * You can use this interface to modify code generated.
 *
 * If you only want to perform some action when ALL beans/DAOs have been generated, use the GeneratorListenerInterface.
 *
 * @experimental !!! This API can have breaking changes between minor versions (but is consistent through patch releases).
 */
interface CodeGeneratorListenerInterface
{
    public function onBaseBeanGenerated(FileGenerator $fileGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration): ?FileGenerator;

    public function onBaseBeanConstructorGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    /**
     * Called when a column is turned into a getter/setter.
     *
     * @return array<int, ?MethodGenerator> Returns an array of 2 methods to be generated for this property. You MUST return the getter (first argument) and setter (second argument) as part of these methods (if you want them to appear in the bean). Return null if you want to delete them.
     */
    public function onBaseBeanPropertyGenerated(?MethodGenerator $getter, ?MethodGenerator $setter, AbstractBeanPropertyDescriptor $propertyDescriptor, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): array;

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
    public function onBaseBeanOneToManyGenerated(MethodGenerator $getter, DirectForeignKeyMethodDescriptor $directForeignKeyMethodDescriptor, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    /**
     * Called when a pivot table is turned into get/has/add/set/remove methods.
     *
     * @return array<int, ?MethodGenerator> Returns an array of methods to be generated for this property. You MUST return the get/add/remove/has/set methods in this order (if you want them to appear in the bean, otherwise return null).
     */
    public function onBaseBeanManyToManyGenerated(?MethodGenerator $getter, ?MethodGenerator $adder, ?MethodGenerator $remover, ?MethodGenerator $hasser, ?MethodGenerator $setter, PivotTableMethodsDescriptor $pivotTableMethodsDescriptor, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): array;

    public function onBaseBeanJsonSerializeGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    public function onBaseBeanCloneGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    public function onBaseDaoGenerated(FileGenerator $fileGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration): ?FileGenerator;

    public function onBaseDaoConstructorGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    public function onBaseDaoSaveGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    public function onBaseDaoFindAllGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    public function onBaseDaoGetByIdGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    public function onBaseDaoDeleteGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    public function onBaseDaoFindGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    public function onBaseDaoFindFromSqlGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    public function onBaseDaoFindFromRawSqlGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    public function onBaseDaoFindOneGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    public function onBaseDaoFindOneFromSqlGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    public function onBaseDaoSetDefaultSortGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;

    public function onBaseDaoFindByIndexGenerated(MethodGenerator $methodGenerator, Index $index, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator;
}
