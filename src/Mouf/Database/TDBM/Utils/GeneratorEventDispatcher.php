<?php


namespace Mouf\Database\TDBM\Utils;


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
     * @param BeanDescriptorInterface[] $beanDescriptors
     */
    public function onGenerate(array $beanDescriptors): void
    {
        foreach ($this->listeners as $listener) {
            $listener->onGenerate($beanDescriptors);
        }
    }
}
