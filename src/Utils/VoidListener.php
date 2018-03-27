<?php
declare(strict_types=1);


namespace TheCodingMachine\TDBM\Utils;

use TheCodingMachine\TDBM\ConfigurationInterface;

/**
 * A listener that does nothing.
 */
class VoidListener implements GeneratorListenerInterface
{

    /**
     * @param ConfigurationInterface $configuration
     * @param BeanDescriptorInterface[] $beanDescriptors
     */
    public function onGenerate(ConfigurationInterface $configuration, array $beanDescriptors): void
    {
        // Let's do nothing.
    }
}
