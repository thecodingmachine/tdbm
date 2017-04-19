<?php


namespace Mouf\Database\TDBM\Utils;


use Doctrine\DBAL\Schema\Index;

/**
 * Listens to events when DAOs and beans are generated and act accordingly on those.
 */
interface GeneratorListenerInterface
{
    public function onEnterBean(BeanDescriptor $beanDescriptor, string $className, string $tableName, string $extends = null);
    public function onColumn(BeanDescriptor $beanDescriptor, AbstractBeanPropertyDescriptor $descriptor, string $columnName);
    public function onMethod(BeanDescriptor $beanDescriptor, MethodDescriptorInterface $descriptor);
    public function onExitBean(BeanDescriptor $beanDescriptor);
    public function onEnterDao(string $className);
    public function onFindMethod(Index $index, string $methodName, string $beanClassName);
    public function onExitDao(string $className);
}