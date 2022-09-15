<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use TheCodingMachine\TDBM\ConfigurationInterface;

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

    /**
     * @param ConfigurationInterface $configuration
     * @param BeanDescriptorInterface[] $beanDescriptors
     */
    public function onGenerate(ConfigurationInterface $configuration, array $beanDescriptors): void
    {
        foreach ($this->listeners as $listener) {
            $listener->onGenerate($configuration, $beanDescriptors);
        }
    }
}
