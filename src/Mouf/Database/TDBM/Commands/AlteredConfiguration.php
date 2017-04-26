<?php


namespace Mouf\Database\TDBM\Commands;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use Mouf\Database\TDBM\ConfigurationInterface;
use Mouf\Database\TDBM\Utils\GeneratorListenerInterface;
use Mouf\Database\TDBM\Utils\NamingStrategyInterface;
use Psr\Log\LoggerInterface;

/**
 * A class to alter any ConfigurationInterface on the fly.
 *
 * The logger can be altered with setLogger method.
 * Useful to dynamically register a logger in the Symfony commands.
 */
class AlteredConfiguration implements ConfigurationInterface
{
    /**
     * @var ConfigurationInterface
     */
    private $configuration;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
        $this->logger = $configuration->getLogger();
    }


    /**
     * @return string
     */
    public function getBeanNamespace(): string
    {
        return $this->configuration->getBeanNamespace();
    }

    /**
     * @return string
     */
    public function getDaoNamespace(): string
    {
        return $this->configuration->getDaoNamespace();
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->configuration->getConnection();
    }

    /**
     * @return Cache
     */
    public function getCache(): Cache
    {
        return $this->configuration->getCache();
    }

    /**
     * @return NamingStrategyInterface
     */
    public function getNamingStrategy(): NamingStrategyInterface
    {
        return $this->configuration->getNamingStrategy();
    }

    /**
     * @return SchemaAnalyzer
     */
    public function getSchemaAnalyzer(): SchemaAnalyzer
    {
        return $this->configuration->getSchemaAnalyzer();
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->configuration->getLogger();
    }

    /**
     * @return GeneratorListenerInterface
     */
    public function getGeneratorEventDispatcher(): GeneratorListenerInterface
    {
        return $this->configuration->getGeneratorEventDispatcher();
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get the Composer file used to detect the path where files should be written.
     * Path is relative to the root directory (this function will typically return 'composer.json' unless you want to write the beans and DAOs in a package).
     *
     * @return null|string
     */
    public function getComposerFile(): string
    {
        return $this->configuration->getComposerFile();
    }
}