<?php


namespace TheCodingMachine\TDBM\Commands;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use TheCodingMachine\TDBM\ConfigurationInterface;
use TheCodingMachine\TDBM\Utils\GeneratorListenerInterface;
use TheCodingMachine\TDBM\Utils\NamingStrategyInterface;
use TheCodingMachine\TDBM\Utils\PathFinder\PathFinderInterface;
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
     * @var LoggerInterface|null
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
     * @return LoggerInterface|null
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
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
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Returns a class able to find the place of a PHP file based on the class name.
     * Useful to find the path where DAOs and beans should be written to.
     *
     * @return PathFinderInterface
     */
    public function getPathFinder(): PathFinderInterface
    {
        return $this->configuration->getPathFinder();
    }
}
