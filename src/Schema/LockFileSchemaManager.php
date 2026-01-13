<?php

namespace TheCodingMachine\TDBM\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Constraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\View;
use TheCodingMachine\TDBM\SchemaLockFileDumper;

/**
 * A DBAL SchemaManager that reads the schema from the lock file instead of reading it from the database.
 * Useful to avoid costly introspection queries at runtime.
 *
 * Acts as an adapter on top of an existing schema manager.
 *
 * @template-covariant T of AbstractPlatform
 * @extends AbstractSchemaManager<T>
 */
class LockFileSchemaManager extends AbstractSchemaManager
{
    private AbstractSchemaManager $schemaManager;
    private SchemaLockFileDumper $schemaLockFileDumper;

    public function __construct(AbstractSchemaManager $schemaManager, SchemaLockFileDumper $schemaLockFileDumper)
    {
        $this->schemaManager = $schemaManager;
        $this->schemaLockFileDumper = $schemaLockFileDumper;
    }

    /**
     * @return T
     */
    public function getDatabasePlatform()
    {
        return $this->schemaManager->getDatabasePlatform();
    }

    public function tryMethod(): mixed
    {
        return $this->schemaManager->tryMethod();
    }

    public function listDatabases(): array
    {
        return $this->schemaManager->listDatabases();
    }

    public function listNamespaceNames(): array
    {
        return $this->schemaManager->listNamespaceNames();
    }

    public function listSequences($database = null): array
    {
        return $this->schemaManager->listSequences($database);
    }

    public function listTableColumns($table, $database = null): array
    {
        return $this->schemaManager->listTableColumns($table, $database);
    }

    public function listTableIndexes($table): array
    {
        return $this->schemaManager->listTableIndexes($table);
    }

    public function tablesExist($names): bool
    {
        return $this->schemaManager->tablesExist($names);
    }

    public function listTableNames(): array
    {
        return $this->schemaManager->listTableNames();
    }

    protected function filterAssetNames($assetNames): array
    {
        return $this->schemaManager->filterAssetNames($assetNames);
    }

    public function listTables(): array
    {
        return $this->schemaManager->listTables();
    }

    public function listTableDetails($name): Table
    {
        return $this->schemaManager->listTableDetails($name);
    }

    public function listViews(): array
    {
        return $this->schemaManager->listViews();
    }

    public function listTableForeignKeys($table, $database = null): array
    {
        return $this->schemaManager->listTableForeignKeys($table, $database);
    }

    public function dropDatabase($database): void
    {
        $this->schemaManager->dropDatabase($database);
    }

    public function dropTable($name): void
    {
        $this->schemaManager->dropTable($name);
    }

    public function dropIndex($index, $table): void
    {
        $this->schemaManager->dropIndex($index, $table);
    }

    public function dropConstraint(Constraint $constraint, $table): void
    {
        $this->schemaManager->dropConstraint($constraint, $table);
    }

    public function dropForeignKey($foreignKey, $table): void
    {
        $this->schemaManager->dropForeignKey($foreignKey, $table);
    }

    public function dropSequence($name): void
    {
        $this->schemaManager->dropSequence($name);
    }

    public function dropView($name): void
    {
        $this->schemaManager->dropView($name);
    }

    public function createDatabase($database): void
    {
        $this->schemaManager->createDatabase($database);
    }

    public function createTable(Table $table): void
    {
        $this->schemaManager->createTable($table);
    }

    public function createSequence($sequence): void
    {
        $this->schemaManager->createSequence($sequence);
    }

    public function createConstraint(Constraint $constraint, $table): void
    {
        $this->schemaManager->createConstraint($constraint, $table);
    }

    public function createIndex(Index $index, $table): void
    {
        $this->schemaManager->createIndex($index, $table);
    }

    public function createForeignKey(ForeignKeyConstraint $foreignKey, $table): void
    {
        $this->schemaManager->createForeignKey($foreignKey, $table);
    }

    public function createView(View $view): void
    {
        $this->schemaManager->createView($view);
    }

    public function dropAndCreateConstraint(Constraint $constraint, $table): void
    {
        $this->schemaManager->dropAndCreateConstraint($constraint, $table);
    }

    public function dropAndCreateIndex(Index $index, $table): void
    {
        $this->schemaManager->dropAndCreateIndex($index, $table);
    }

    public function dropAndCreateForeignKey(ForeignKeyConstraint $foreignKey, $table): void
    {
        $this->schemaManager->dropAndCreateForeignKey($foreignKey, $table);
    }

    public function dropAndCreateSequence(Sequence $sequence): void
    {
        $this->schemaManager->dropAndCreateSequence($sequence);
    }

    public function dropAndCreateTable(Table $table): void
    {
        $this->schemaManager->dropAndCreateTable($table);
    }

    public function dropAndCreateDatabase($database): void
    {
        $this->schemaManager->dropAndCreateDatabase($database);
    }

    public function dropAndCreateView(View $view): void
    {
        $this->schemaManager->dropAndCreateView($view);
    }

    public function alterTable(TableDiff $tableDiff): void
    {
        $this->schemaManager->alterTable($tableDiff);
    }

    public function renameTable($name, $newName): void
    {
        $this->schemaManager->renameTable($name, $newName);
    }

    protected function _getPortableDatabasesList($databases): array
    {
        return $this->schemaManager->_getPortableDatabasesList($databases);
    }

    protected function getPortableNamespacesList(array $namespaces): array
    {
        return $this->schemaManager->getPortableNamespacesList($namespaces);
    }

    protected function _getPortableDatabaseDefinition($database): mixed
    {
        return $this->schemaManager->_getPortableDatabaseDefinition($database);
    }

    protected function getPortableNamespaceDefinition(array $namespace): mixed
    {
        return $this->schemaManager->getPortableNamespaceDefinition($namespace);
    }

    protected function _getPortableSequencesList($sequences): array
    {
        return $this->schemaManager->_getPortableSequencesList($sequences);
    }

    protected function _getPortableSequenceDefinition($sequence): Sequence
    {
        return $this->schemaManager->_getPortableSequenceDefinition($sequence);
    }

    protected function _getPortableTableColumnList($table, $database, $tableColumns): array
    {
        return $this->schemaManager->_getPortableTableColumnList($table, $database, $tableColumns);
    }

    protected function _getPortableTableIndexesList($tableIndexes, $tableName = null): array
    {
        return $this->schemaManager->_getPortableTableIndexesList($tableIndexes, $tableName);
    }

    protected function _getPortableTablesList($tables): array
    {
        return $this->schemaManager->_getPortableTablesList($tables);
    }

    protected function _getPortableTableDefinition($table): string
    {
        return $this->schemaManager->_getPortableTableDefinition($table);
    }

    protected function _getPortableViewsList($views): array
    {
        return $this->schemaManager->_getPortableViewsList($views);
    }

    /**
     * @return View|false
     */
    protected function _getPortableViewDefinition($view)
    {
        return $this->schemaManager->_getPortableViewDefinition($view);
    }

    protected function _getPortableTableForeignKeysList($tableForeignKeys): array
    {
        return $this->schemaManager->_getPortableTableForeignKeysList($tableForeignKeys);
    }

    protected function _getPortableTableForeignKeyDefinition($tableForeignKey): ForeignKeyConstraint
    {
        return $this->schemaManager->_getPortableTableForeignKeyDefinition($tableForeignKey);
    }

    protected function _execSql($sql): void
    {
        $this->schemaManager->_execSql($sql);
    }

    public function createSchema(): Schema
    {
        return $this->schemaLockFileDumper->getSchema();
    }

    public function createSchemaConfig(): SchemaConfig
    {
        return $this->schemaManager->createSchemaConfig();
    }

    public function getSchemaSearchPaths(): array
    {
        return $this->schemaManager->getSchemaSearchPaths();
    }

    public function extractDoctrineTypeFromComment($comment, $currentType): string
    {
        return $this->schemaManager->extractDoctrineTypeFromComment($comment, $currentType);
    }

    public function removeDoctrineTypeFromComment($comment, $type): ?string
    {
        return $this->schemaManager->removeDoctrineTypeFromComment($comment, $type);
    }

    protected function _getPortableTableColumnDefinition($tableColumn): Column
    {
        return $this->schemaManager->_getPortableTableColumnDefinition($tableColumn);
    }
}
