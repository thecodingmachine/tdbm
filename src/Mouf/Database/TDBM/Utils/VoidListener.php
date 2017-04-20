<?php


namespace Mouf\Database\TDBM\Utils;


use Doctrine\DBAL\Schema\Index;

/**
 * A listener that does nothing.
 */
class VoidListener implements GeneratorListenerInterface
{
    /**
     * @param BeanDescriptorInterface[] $beanDescriptors
     */
    public function onGenerate(array $beanDescriptors): void
    {
    }
}