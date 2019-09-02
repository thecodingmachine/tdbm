<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM;

use BrainDiminished\SchemaVersionControl\SchemaVersionControlService;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\Type;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use TheCodingMachine\TDBM\Utils\ImmutableCaster;
use TheCodingMachine\TDBM\Utils\RootProjectLocator;

/**
 * This class is used to analyze the schema and return valuable information / hints.
 */
class TDBMSchemaAnalyzer
{
    const schemaFileName =  'tdbm.lock.yml';

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
     * @var SchemaVersionControlService
     */
    private $schemaVersionControlService;

    /**
     * @param Connection     $connection     The DBAL DB connection to use
     * @param Cache          $cache          A cache service to be used
     * @param SchemaAnalyzer $schemaAnalyzer The schema analyzer that will be used to find shortest paths...
     *                                       Will be automatically created if not passed
     */
    public function __construct(Connection $connection, Cache $cache, SchemaAnalyzer $schemaAnalyzer)
    {
        $this->connection = $connection;
        $this->cache = $cache;
        $this->schemaAnalyzer = $schemaAnalyzer;
        $this->schemaVersionControlService = new SchemaVersionControlService($this->connection, self::getLockFilePath());
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

    public static function getLockFilePath(): string
    {
        return RootProjectLocator::getRootLocationPath().self::schemaFileName;
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
            } elseif (!file_exists(self::getLockFilePath())) {
                throw new TDBMException('No tdbm lock file found. Please regenerate DAOs and Beans.');
            } else {
                $this->schema = $this->schemaVersionControlService->loadSchemaFile();
                ImmutableCaster::castSchemaToImmutable($this->schema);
                $this->cache->save($cacheKey, $this->schema);
            }
        }

        return $this->schema;
    }

    public function generateLockFile(): void
    {
        $this->schemaVersionControlService->dumpSchema();
    }

    /**
     * Returns the list of pivot tables linked to table $tableName.
     *
     * @param string $tableName
     *
     * @return string[]
     */
    public function getPivotTableLinkedToTable(string $tableName): array
    {
        $cacheKey = $this->getCachePrefix().'_pivottables_link_'.$tableName;
        if ($this->cache->contains($cacheKey)) {
            return $this->cache->fetch($cacheKey);
        }

        $pivotTables = [];

        $junctionTables = $this->schemaAnalyzer->detectJunctionTables(true);
        foreach ($junctionTables as $table) {
            $fks = $table->getForeignKeys();
            foreach ($fks as $fk) {
                if ($fk->getForeignTableName() == $tableName) {
                    $pivotTables[] = $table->getName();
                    break;
                }
            }
        }

        $this->cache->save($cacheKey, $pivotTables);

        return $pivotTables;
    }

    /**
     * Returns the list of foreign keys pointing to the table represented by this bean, excluding foreign keys
     * from junction tables and from inheritance.
     * It will also suppress doubles if 2 foreign keys are using the same columns.
     *
     * @return ForeignKeyConstraint[]
     */
    public function getIncomingForeignKeys(string $tableName): array
    {
        $junctionTables = $this->schemaAnalyzer->detectJunctionTables(true);
        $junctionTableNames = array_map(function (Table $table) {
            return $table->getName();
        }, $junctionTables);
        $childrenRelationships = $this->schemaAnalyzer->getChildrenRelationships($tableName);

        $fks = [];
        foreach ($this->getSchema()->getTables() as $table) {
            $uniqueForeignKeys = $this->removeDuplicates($table->getForeignKeys());
            foreach ($uniqueForeignKeys as $fk) {
                if ($fk->getForeignTableName() === $tableName) {
                    if (in_array($fk->getLocalTableName(), $junctionTableNames)) {
                        continue;
                    }
                    foreach ($childrenRelationships as $childFk) {
                        if ($fk->getLocalTableName() === $childFk->getLocalTableName() && $fk->getUnquotedLocalColumns() === $childFk->getUnquotedLocalColumns()) {
                            continue 2;
                        }
                    }
                    $fks[] = $fk;
                }
            }
        }

        return $fks;
    }

    /**
     * Remove duplicate foreign keys (assumes that all foreign yes are from the same local table)
     *
     * @param ForeignKeyConstraint[] $foreignKeys
     * @return ForeignKeyConstraint[]
     */
    private function removeDuplicates(array $foreignKeys): array
    {
        $fks = [];
        foreach ($foreignKeys as $foreignKey) {
            $key = implode('__`__', $foreignKey->getUnquotedLocalColumns());
            if (!isset($fks[$key])) {
                $fks[$key] = $foreignKey;
            }
        }

        return array_values($fks);
    }
}
