<?php


namespace TheCodingMachine\TDBM;

use BrainDiminished\SchemaVersionControl\SchemaVersionControlService;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use TheCodingMachine\TDBM\Utils\ColumnsReorderer;
use TheCodingMachine\TDBM\Utils\ImmutableCaster;
use function array_map;
use function array_values;
use function file_exists;
use function hash;
use function implode;
use function in_array;

/**
 * Load / save schema in the tdbm.lock file.
 */
class SchemaLockFileDumper
{
    private $lockFilePath;
    private $connection;

    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var string
     */
    private $cachePrefix;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var SchemaVersionControlService
     */
    private $schemaVersionControlService;

    /**
     * @param Connection $connection The DBAL DB connection to use
     * @param Cache $cache A cache service to be used
     * @param string $lockFilePath The path for the lock file which will store the database schema
     */
    public function __construct(Connection $connection, Cache $cache, string $lockFilePath)
    {
        $this->connection = $connection;
        $this->cache = $cache;
        $this->lockFilePath = $lockFilePath;
        $this->schemaVersionControlService = new SchemaVersionControlService($this->connection, $this->lockFilePath);
    }

    /**
     * Returns a unique ID for the current connection. Useful for namespacing cache entries in the current connection.
     *
     * @return string
     */
    public function getCachePrefix(): string
    {
        if ($this->cachePrefix === null) {
            $this->cachePrefix = hash('md4', $this->connection->getHost().'-'.$this->connection->getPort().'-'.$this->connection->getDatabase().'-'.$this->connection->getDriver()->getName());
        }

        return $this->cachePrefix;
    }

    public function getLockFilePath(): string
    {
        return $this->lockFilePath;
    }

    /**
     * Returns the (cached) schema.
     */
    public function getSchema(bool $ignoreCache = false): Schema
    {
        if ($this->schema === null) {
            $cacheKey = $this->getCachePrefix().'_immutable_schema';
            if (!$ignoreCache && $this->cache->contains($cacheKey)) {
                $this->schema = $this->cache->fetch($cacheKey);
            } elseif (!file_exists($this->getLockFilePath())) {
                throw new TDBMException('No tdbm lock file found. Please regenerate DAOs and Beans.');
            } else {
                $this->schema = $this->schemaVersionControlService->loadSchemaFile();
                ImmutableCaster::castSchemaToImmutable($this->schema);
                ColumnsReorderer::reorderTableColumns($this->schema);
                $this->cache->save($cacheKey, $this->schema);
            }
        }

        return $this->schema;
    }

    public function generateLockFile(): void
    {
        $this->schemaVersionControlService->dumpSchema();
        \chmod($this->getLockFilePath(), 0664);
    }
}
