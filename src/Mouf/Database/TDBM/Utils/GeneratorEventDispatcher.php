<?php


namespace Mouf\Database\TDBM\Utils;


use Doctrine\DBAL\Schema\Index;

class GeneratorEventDispatcher implements GeneratorListenerInterface
{
    /**
     * @var GeneratorListenerInterface[]
     */
    private $listeners;

    /**
     * GeneratorEventDispatcher constructor.
     * @param GeneratorListenerInterface[] $listeners
     */
    public function __construct(array $listeners)
    {
        $this->listeners = $listeners;
    }

    public function onEnterBean(BeanDescriptor $beanDescriptor, string $className, string $tableName, string $extends = null)
    {
        foreach ($this->listeners as $listener) {
            $listener->onEnterBean($beanDescriptor, $className, $tableName, $extends);
        }
    }

    public function onColumn(BeanDescriptor $beanDescriptor, AbstractBeanPropertyDescriptor $descriptor, string $columnName)
    {
        foreach ($this->listeners as $listener) {
            $listener->onColumn($beanDescriptor, $descriptor, $columnName);
        }
    }

    public function onMethod(BeanDescriptor $beanDescriptor, MethodDescriptorInterface $descriptor)
    {
        foreach ($this->listeners as $listener) {
            $listener->onMethod($beanDescriptor, $descriptor);
        }
    }

    public function onExitBean(BeanDescriptor $beanDescriptor)
    {
        foreach ($this->listeners as $listener) {
            $listener->onExitBean($beanDescriptor);
        }
    }

    public function onEnterDao(string $className)
    {
        foreach ($this->listeners as $listener) {
            $listener->onEnterDao($className);
        }
    }

    public function onFindMethod(Index $index, string $methodName, string $beanClassName)
    {
        foreach ($this->listeners as $listener) {
            $listener->onFindMethod($index, $methodName, $beanClassName);
        }
    }

    public function onExitDao(string $className)
    {
        foreach ($this->listeners as $listener) {
            $listener->onExitDao($className);
        }
    }
}
