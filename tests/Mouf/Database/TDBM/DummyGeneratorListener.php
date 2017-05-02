<?php


namespace Mouf\Database\TDBM;

use Mouf\Database\TDBM\Utils\BeanDescriptorInterface;
use Mouf\Database\TDBM\Utils\GeneratorListenerInterface;

class DummyGeneratorListener implements GeneratorListenerInterface
{
    /**
     * @var ConfigurationInterface
     */
    private $configuration;

    /**
     * @var BeanDescriptorInterface[]
     */
    private $beanDescriptors = [];

    /**
     * @param ConfigurationInterface $configuration
     * @param BeanDescriptorInterface[] $beanDescriptors
     */
    public function onGenerate(ConfigurationInterface $configuration, array $beanDescriptors): void
    {
        $this->configuration = $configuration;
        $this->beanDescriptors = $beanDescriptors;
    }

    /**
     * @return ConfigurationInterface
     */
    public function getConfiguration(): ConfigurationInterface
    {
        return $this->configuration;
    }

    /**
     * @return BeanDescriptorInterface[]
     */
    public function getBeanDescriptors(): array
    {
        return $this->beanDescriptors;
    }
}
