<?php


namespace TheCodingMachine\TDBM;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\DBAL\Connection;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use TheCodingMachine\TDBM\Utils\GeneratorEventDispatcher;
use TheCodingMachine\TDBM\Utils\GeneratorListenerInterface;
use TheCodingMachine\TDBM\Utils\NamingStrategyInterface;
use TheCodingMachine\TDBM\Utils\PathFinder\PathFinderInterface;
use Psr\Log\LoggerInterface;
use TheCodingMachine\TDBM\Utils\PathFinder\PathFinder;

class Configuration implements ConfigurationInterface
{

    /**
     * @var string
     */
    private $beanNamespace;
    /**
     * @var string
     */
    private $daoNamespace;
    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var Cache
     */
    private $cache;
    /**
     * @var SchemaAnalyzer
     */
    private $schemaAnalyzer;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var GeneratorListenerInterface
     */
    private $generatorEventDispatcher;
    /**
     * @var NamingStrategyInterface
     */
    private $namingStrategy;
    /**
     * @var PathFinderInterface
     */
    private $pathFinder;

    /**
     * @param string $beanNamespace The namespace hosting the beans
     * @param string $daoNamespace The namespace hosting the DAOs
     * @param Connection $connection The connection to the database
     * @param Cache|null $cache The Doctrine cache to store database metadata
     * @param SchemaAnalyzer|null $schemaAnalyzer The schema analyzer that will be used to find shortest paths... Will be automatically created if not passed
     * @param LoggerInterface|null $logger The logger
     * @param GeneratorListenerInterface[] $generatorListeners A list of listeners that will be triggered when beans/daos are generated
     */
    public function __construct(string $beanNamespace, string $daoNamespace, Connection $connection, NamingStrategyInterface $namingStrategy, Cache $cache = null, SchemaAnalyzer $schemaAnalyzer = null, LoggerInterface $logger = null, array $generatorListeners = [])
    {
        $this->beanNamespace = rtrim($beanNamespace, '\\');
        $this->daoNamespace = rtrim($daoNamespace, '\\');
        $this->connection = $connection;
        $this->namingStrategy = $namingStrategy;
        if ($cache !== null) {
            $this->cache = $cache;
        } else {
            $this->cache = new VoidCache();
        }
        if ($schemaAnalyzer !== null) {
            $this->schemaAnalyzer = $schemaAnalyzer;
        } else {
            $this->schemaAnalyzer = new SchemaAnalyzer($this->connection->getSchemaManager(), $this->cache, $this->getConnectionUniqueId());
        }
        $this->logger = $logger;
        $this->generatorEventDispatcher = new GeneratorEventDispatcher($generatorListeners);
        $this->pathFinder = new PathFinder();
    }

    /**
     * @return string
     */
    public function getBeanNamespace(): string
    {
        return $this->beanNamespace;
    }

    /**
     * @return string
     */
    public function getDaoNamespace(): string
    {
        return $this->daoNamespace;
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @return NamingStrategyInterface
     */
    public function getNamingStrategy(): NamingStrategyInterface
    {
        return $this->namingStrategy;
    }

    /**
     * @return Cache
     */
    public function getCache(): Cache
    {
        return $this->cache;
    }

    /**
     * @return SchemaAnalyzer
     */
    public function getSchemaAnalyzer(): SchemaAnalyzer
    {
        return $this->schemaAnalyzer;
    }

    /**
     * @return LoggerInterface
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
        return $this->generatorEventDispatcher;
    }



    /**
     * Creates a unique cache key for the current connection.
     *
     * @return string
     */
    private function getConnectionUniqueId(): string
    {
        return hash('md4', $this->connection->getHost().'-'.$this->connection->getPort().'-'.$this->connection->getDatabase().'-'.$this->connection->getDriver()->getName());
    }

    /**
     * Returns a class able to find the place of a PHP file based on the class name.
     * Useful to find the path where DAOs and beans should be written to.
     *
     * @return PathFinderInterface
     */
    public function getPathFinder(): PathFinderInterface
    {
        return $this->pathFinder;
    }

    /**
     * @param PathFinderInterface $pathFinder
     */
    public function setPathFinder(PathFinderInterface $pathFinder)
    {
        $this->pathFinder = $pathFinder;
    }
}
