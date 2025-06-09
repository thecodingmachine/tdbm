<?php

namespace TheCodingMachine\TDBM;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use TheCodingMachine\TDBM\SchemaVersionControl\SchemaVersionControlService;
use TheCodingMachine\TDBM\Utils\ColumnsReorderer;
use TheCodingMachine\TDBM\Utils\ImmutableCaster;

use function file_exists;
use function hash;
use function serialize;

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
        return $this->cachePrefix ??= hash('md4', serialize($this->connection->getParams()));
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
