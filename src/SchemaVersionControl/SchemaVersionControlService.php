<?php

namespace TheCodingMachine\TDBM\SchemaVersionControl;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Yaml\Yaml;

/**
 * SchemaVersionControlService is the main class of this library. It provides useful methods for managing a database
 * schema, whether to save the current and share current configuration in YAML format, or update the schema to match
 * a new configuration.
 */
class SchemaVersionControlService
{
    /** @var Connection */
    private $connection;

    /** @var string */
    private $schemaFile;

    /**
     * SchemaVersionControlService constructor.
     * @param Connection $connection
     * @param string $schemaFile Path to config file containing top object `schema`.
     */
    public function __construct(Connection $connection, string $schemaFile)
    {
        $this->connection = $connection;
        $this->schemaFile = $schemaFile;
    }

    /**
     * Load schema from config file.
     * @return Schema
     */
    public function loadSchemaFile(): Schema
    {
        if (!file_exists($this->schemaFile)) {
            return new Schema();
        }

        $content = file_get_contents($this->schemaFile);
        $desc = Yaml::parse($content);
        if (empty($desc)) {
            return new Schema();
        }

        $builder = new SchemaBuilder();
        return $builder->build($desc['schema']);
    }

    /**
     * Write current database schema in config file
     */
    public function dumpSchema(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $schemaConfig = $schemaManager->createSchemaConfig();
        $schema = $schemaManager->createSchema();
        $normalizer = new SchemaNormalizer();
        $desc = $normalizer->normalize($schema, $schemaConfig);
        $yamlSchema = Yaml::dump(['schema' => $desc], 10, 2);
        $directory = dirname($this->schemaFile);
        if (!file_exists($directory)) {
            if (mkdir($directory, 0666, true) === false) {
                throw new \RuntimeException('Could not create directory '.$directory);
            }
        }
        if (file_put_contents($this->schemaFile, $yamlSchema) === false) {
            throw new \RuntimeException('Could not edit dump file '.$this->schemaFile);
        }
    }
}
