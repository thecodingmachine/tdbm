<?php
namespace Mouf\Database\TDBM;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use Mouf\Database\TDBM\Utils\AbstractBeanPropertyDescriptor;

/**
 * This class is used to analyze the schema and return valuable information / hints.
 */
class TDBMSchemaAnalyzer
{

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
     * @var SchemaAnalyzer
     */
    private $schemaAnalyzer;

    /**
     * @param Connection $connection The DBAL DB connection to use
     * @param Cache $cache A cache service to be used
     * @param SchemaAnalyzer $schemaAnalyzer The schema analyzer that will be used to find shortest paths...
     * 										 Will be automatically created if not passed.
     */
    public function __construct(Connection $connection, Cache $cache, SchemaAnalyzer $schemaAnalyzer) {
        $this->connection = $connection;
        $this->cache = $cache;
        $this->schemaAnalyzer = $schemaAnalyzer;
    }

    /**
     * Returns a unique ID for the current connection. Useful for namespacing cache entries in the current connection.
     * @return string
     */
    public function getCachePrefix() {
        if ($this->cachePrefix === null) {
            $this->cachePrefix = hash('md4', $this->connection->getHost()."-".$this->connection->getPort()."-".$this->connection->getDatabase()."-".$this->connection->getDriver()->getName());
        }
        return $this->cachePrefix;
    }

    /**
     * Returns the (cached) schema.
     *
     * @return Schema
     */
    public function getSchema() {
        if ($this->schema === null) {
            $cacheKey = $this->getCachePrefix().'_schema';
            if ($this->cache->contains($cacheKey)) {
                $this->schema = $this->cache->fetch($cacheKey);
            } else {
                $this->schema = $this->connection->getSchemaManager()->createSchema();
                $this->cache->save($cacheKey, $this->schema);
            }
        }
        return $this->schema;
    }


}
