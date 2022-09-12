<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\DBAL\Connection;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use TheCodingMachine\TDBM\Schema\LockFileSchemaManager;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;
use TheCodingMachine\TDBM\Utils\Annotation\Autoincrement;
use TheCodingMachine\TDBM\Utils\Annotation\UUID;
use TheCodingMachine\TDBM\Utils\BaseCodeGeneratorListener;
use TheCodingMachine\TDBM\Utils\CodeGeneratorEventDispatcher;
use TheCodingMachine\TDBM\Utils\CodeGeneratorListenerInterface;
use TheCodingMachine\TDBM\Utils\DefaultNamingStrategy;
use TheCodingMachine\TDBM\Utils\GeneratorEventDispatcher;
use TheCodingMachine\TDBM\Utils\GeneratorListenerInterface;
use TheCodingMachine\TDBM\Utils\NamingStrategyInterface;
use TheCodingMachine\TDBM\Utils\PathFinder\PathFinderInterface;
use Psr\Log\LoggerInterface;
use TheCodingMachine\TDBM\Utils\PathFinder\PathFinder;
use TheCodingMachine\TDBM\Utils\RootProjectLocator;

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
    /** @var string */
    private $resultIteratorNamespace;
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
     * @var LoggerInterface|null
     */
    private $logger;
    /**
     * @var GeneratorListenerInterface
     */
    private $generatorEventDispatcher;
    /**
     * @var CodeGeneratorListenerInterface
     */
    private $codeGeneratorListener;
    /**
     * @var NamingStrategyInterface
     */
    private $namingStrategy;
    /**
     * @var PathFinderInterface
     */
    private $pathFinder;
    /**
     * @var AnnotationParser
     */
    private $annotationParser;
    private $lockFilePath;

    /**
     * @param string $beanNamespace The namespace hosting the beans
     * @param string $daoNamespace The namespace hosting the DAOs
     * @param string $resultIteratorNamespace The namespace hosting the ResultIterators
     * @param Connection $connection The connection to the database
     * @param NamingStrategyInterface|null $namingStrategy
     * @param Cache|null $cache The Doctrine cache to store database metadata
     * @param SchemaAnalyzer|null $schemaAnalyzer The schema analyzer that will be used to find shortest paths... Will be automatically created if not passed
     * @param LoggerInterface|null $logger The logger
     * @param GeneratorListenerInterface[] $generatorListeners A list of listeners that will be triggered when beans/daos are generated
     * @param AnnotationParser|null $annotationParser
     * @param CodeGeneratorListenerInterface[] $codeGeneratorListeners A list of listeners that can alter code generation of each bean/dao
     * @param string|null $lockFilePath
     * @throws \Mouf\Database\SchemaAnalyzer\SchemaAnalyzerException
     */
    public function __construct(
        string $beanNamespace,
        string $daoNamespace,
        Connection $connection,
        NamingStrategyInterface $namingStrategy = null,
        Cache $cache = null,
        SchemaAnalyzer $schemaAnalyzer = null,
        LoggerInterface $logger = null,
        array $generatorListeners = [],
        AnnotationParser $annotationParser = null,
        array $codeGeneratorListeners = [],
        string $resultIteratorNamespace = null,
        string $lockFilePath = null
    ) {
        $this->beanNamespace = rtrim($beanNamespace, '\\');
        $this->daoNamespace = rtrim($daoNamespace, '\\');
        if ($resultIteratorNamespace === null) {
            $baseNamespace = explode('\\', $this->daoNamespace);
            array_pop($baseNamespace);
            $baseNamespace[] = 'ResultIterator';
            $resultIteratorNamespace = implode('\\', $baseNamespace);
        }
        $this->resultIteratorNamespace = rtrim($resultIteratorNamespace, '\\');
        $this->connection = $connection;
        if ($cache !== null) {
            $this->cache = $cache;
        } else {
            $this->cache = new VoidCache();
        }
        $this->lockFilePath = $lockFilePath;
        $schemaLockFileDumper = new SchemaLockFileDumper($this->connection, $this->cache, $this->getLockFilePath());
        $lockFileSchemaManager = new LockFileSchemaManager($this->connection->getSchemaManager(), $schemaLockFileDumper);
        if ($schemaAnalyzer !== null) {
            $this->schemaAnalyzer = $schemaAnalyzer;
        } else {
            $this->schemaAnalyzer = new SchemaAnalyzer($lockFileSchemaManager, $this->cache, $this->getConnectionUniqueId());
        }
        $this->logger = $logger;
        $this->generatorEventDispatcher = new GeneratorEventDispatcher($generatorListeners);
        $this->pathFinder = new PathFinder();
        $this->annotationParser = $annotationParser ?: AnnotationParser::buildWithDefaultAnnotations([]);
        $this->codeGeneratorListener = new CodeGeneratorEventDispatcher($codeGeneratorListeners);
        $this->namingStrategy = $namingStrategy ?: new DefaultNamingStrategy($this->annotationParser, $lockFileSchemaManager);
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
     * @return string
     */
    public function getResultIteratorNamespace(): string
    {
        return $this->resultIteratorNamespace;
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
     * @return CodeGeneratorListenerInterface
     */
    public function getCodeGeneratorListener(): CodeGeneratorListenerInterface
    {
        return $this->codeGeneratorListener;
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
    public function setPathFinder(PathFinderInterface $pathFinder): void
    {
        $this->pathFinder = $pathFinder;
    }

    /**
     * @return AnnotationParser
     */
    public function getAnnotationParser(): AnnotationParser
    {
        return $this->annotationParser;
    }

    /**
     * @internal
     */
    public static function getDefaultLockFilePath(): string
    {
        return RootProjectLocator::getRootLocationPath().'tdbm.lock.yml';
    }

    public function getLockFilePath(): string
    {
        return $this->lockFilePath ?: self::getDefaultLockFilePath();
    }
}
