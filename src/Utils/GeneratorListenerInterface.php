<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use TheCodingMachine\TDBM\ConfigurationInterface;

/**
 * Listens to events when DAOs and beans are generated and act accordingly on those.
 *
 * The onGenerate method is triggered once, when all DAOs and Beans have been generated.
 * If you want to act on the code generated, on the fly, have a look at the CodeGeneratorListenerInterface instead.
 */
interface GeneratorListenerInterface
{
    /**
     * @param ConfigurationInterface $configuration
     * @param BeanDescriptorInterface[] $beanDescriptors
     */
    public function onGenerate(ConfigurationInterface $configuration, array $beanDescriptors): void;
}
