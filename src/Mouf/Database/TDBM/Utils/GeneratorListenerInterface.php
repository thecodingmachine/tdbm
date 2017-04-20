<?php


namespace Mouf\Database\TDBM\Utils;

/**
 * Listens to events when DAOs and beans are generated and act accordingly on those.
 */
interface GeneratorListenerInterface
{
    /**
     * @param BeanDescriptorInterface[] $beanDescriptors
     */
    public function onGenerate(array $beanDescriptors) : void;
}
