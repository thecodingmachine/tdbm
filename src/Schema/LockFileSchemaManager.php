<?php


namespace TheCodingMachine\TDBM\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Constraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
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
 */
class LockFileSchemaManager extends AbstractSchemaManager
{
    /**
     * @var AbstractSchemaManager
     */
    private $schemaManager;
    /**
     * @var SchemaLockFileDumper
     */
    private $schemaLockFileDumper;

    public function __construct(AbstractSchemaManager $schemaManager, SchemaLockFileDumper $schemaLockFileDumper)
    {
        $this->schemaManager = $schemaManager;
        $this->schemaLockFileDumper = $schemaLockFileDumper;
    }

    public function getDatabasePlatform()
    {
        return $this->schemaManager->getDatabasePlatform();
    }

    public function tryMethod()
    {
        return $this->schemaManager->tryMethod();
    }

    public function listDatabases()
    {
        return $this->schemaManager->listDatabases();
    }

    public function listNamespaceNames()
    {
        return $this->schemaManager->listNamespaceNames();
    }

    public function listSequences($database = null)
    {
        return $this->schemaManager->listSequences($database);
    }

    public function listTableColumns($table, $database = null)
    {
        return $this->schemaManager->listTableColumns($table, $database);
    }

    public function listTableIndexes($table)
    {
        return $this->schemaManager->listTableIndexes($table);
    }

    public function tablesExist($tableNames)
    {
        return $this->schemaManager->tablesExist($tableNames);
    }

    public function listTableNames()
    {
        return $this->schemaManager->listTableNames();
    }

    protected function filterAssetNames($assetNames)
    {
        return $this->schemaManager->filterAssetNames($assetNames);
    }

    protected function getFilterSchemaAssetsExpression()
    {
        return $this->schemaManager->getFilterSchemaAssetsExpression();
    }

    public function listTables()
    {
        return $this->schemaManager->listTables();
    }

    public function listTableDetails($tableName)
    {
        return $this->schemaManager->listTableDetails($tableName);
    }

    public function listViews()
    {
        return $this->schemaManager->listViews();
    }

    public function listTableForeignKeys($table, $database = null)
    {
        return $this->schemaManager->listTableForeignKeys($table, $database);
    }

    public function dropDatabase($database)
    {
        $this->schemaManager->dropDatabase($database);
    }

    public function dropTable($tableName)
    {
        $this->schemaManager->dropTable($tableName);
    }

    public function dropIndex($index, $table)
    {
        $this->schemaManager->dropIndex($index, $table);
    }

    public function dropConstraint(Constraint $constraint, $table)
    {
        $this->schemaManager->dropConstraint($constraint, $table);
    }

    public function dropForeignKey($foreignKey, $table)
    {
        $this->schemaManager->dropForeignKey($foreignKey, $table);
    }

    public function dropSequence($name)
    {
        $this->schemaManager->dropSequence($name);
    }

    public function dropView($name)
    {
        $this->schemaManager->dropView($name);
    }

    public function createDatabase($database)
    {
        $this->schemaManager->createDatabase($database);
    }

    public function createTable(Table $table)
    {
        $this->schemaManager->createTable($table);
    }

    public function createSequence($sequence)
    {
        $this->schemaManager->createSequence($sequence);
    }

    public function createConstraint(Constraint $constraint, $table)
    {
        $this->schemaManager->createConstraint($constraint, $table);
    }

    public function createIndex(Index $index, $table)
    {
        $this->schemaManager->createIndex($index, $table);
    }

    public function createForeignKey(ForeignKeyConstraint $foreignKey, $table)
    {
        $this->schemaManager->createForeignKey($foreignKey, $table);
    }

    public function createView(View $view)
    {
        $this->schemaManager->createView($view);
    }

    public function dropAndCreateConstraint(Constraint $constraint, $table)
    {
        $this->schemaManager->dropAndCreateConstraint($constraint, $table);
    }

    public function dropAndCreateIndex(Index $index, $table)
    {
        $this->schemaManager->dropAndCreateIndex($index, $table);
    }

    public function dropAndCreateForeignKey(ForeignKeyConstraint $foreignKey, $table)
    {
        $this->schemaManager->dropAndCreateForeignKey($foreignKey, $table);
    }

    public function dropAndCreateSequence(Sequence $sequence)
    {
        $this->schemaManager->dropAndCreateSequence($sequence);
    }

    public function dropAndCreateTable(Table $table)
    {
        $this->schemaManager->dropAndCreateTable($table);
    }

    public function dropAndCreateDatabase($database)
    {
        $this->schemaManager->dropAndCreateDatabase($database);
    }

    public function dropAndCreateView(View $view)
    {
        $this->schemaManager->dropAndCreateView($view);
    }

    public function alterTable(TableDiff $tableDiff)
    {
        $this->schemaManager->alterTable($tableDiff);
    }

    public function renameTable($name, $newName)
    {
        $this->schemaManager->renameTable($name, $newName);
    }

    protected function _getPortableDatabasesList($databases)
    {
        return $this->schemaManager->_getPortableDatabasesList($databases);
    }

    protected function getPortableNamespacesList(array $namespaces)
    {
        return $this->schemaManager->getPortableNamespacesList($namespaces);
    }

    protected function _getPortableDatabaseDefinition($database)
    {
        return $this->schemaManager->_getPortableDatabaseDefinition($database);
    }

    protected function getPortableNamespaceDefinition(array $namespace)
    {
        return $this->schemaManager->getPortableNamespaceDefinition($namespace);
    }

    protected function _getPortableFunctionsList($functions)
    {
        return $this->schemaManager->_getPortableFunctionsList($functions);
    }

    protected function _getPortableFunctionDefinition($function)
    {
        return $this->schemaManager->_getPortableFunctionDefinition($function);
    }

    protected function _getPortableTriggersList($triggers)
    {
        return $this->schemaManager->_getPortableTriggersList($triggers);
    }

    protected function _getPortableTriggerDefinition($trigger)
    {
        return $this->schemaManager->_getPortableTriggerDefinition($trigger);
    }

    protected function _getPortableSequencesList($sequences)
    {
        return $this->schemaManager->_getPortableSequencesList($sequences);
    }

    protected function _getPortableSequenceDefinition($sequence)
    {
        return $this->schemaManager->_getPortableSequenceDefinition($sequence);
    }

    protected function _getPortableTableColumnList($table, $database, $tableColumns)
    {
        return $this->schemaManager->_getPortableTableColumnList($table, $database, $tableColumns);
    }

    protected function _getPortableTableIndexesList($tableIndexRows, $tableName = null)
    {
        return $this->schemaManager->_getPortableTableIndexesList($tableIndexRows, $tableName);
    }

    protected function _getPortableTablesList($tables)
    {
        return $this->schemaManager->_getPortableTablesList($tables);
    }

    protected function _getPortableTableDefinition($table)
    {
        return $this->schemaManager->_getPortableTableDefinition($table);
    }

    protected function _getPortableUsersList($users)
    {
        return $this->schemaManager->_getPortableUsersList($users);
    }

    protected function _getPortableUserDefinition($user)
    {
        return $this->schemaManager->_getPortableUserDefinition($user);
    }

    protected function _getPortableViewsList($views)
    {
        return $this->schemaManager->_getPortableViewsList($views);
    }

    protected function _getPortableViewDefinition($view)
    {
        return $this->schemaManager->_getPortableViewDefinition($view);
    }

    protected function _getPortableTableForeignKeysList($tableForeignKeys)
    {
        return $this->schemaManager->_getPortableTableForeignKeysList($tableForeignKeys);
    }

    protected function _getPortableTableForeignKeyDefinition($tableForeignKey)
    {
        return $this->schemaManager->_getPortableTableForeignKeyDefinition($tableForeignKey);
    }

    protected function _execSql($sql)
    {
        $this->schemaManager->_execSql($sql);
    }

    public function createSchema()
    {
        return $this->schemaLockFileDumper->getSchema();
    }

    public function createSchemaConfig()
    {
        return $this->schemaManager->createSchemaConfig();
    }

    public function getSchemaSearchPaths()
    {
        return $this->schemaManager->getSchemaSearchPaths();
    }

    public function extractDoctrineTypeFromComment($comment, $currentType)
    {
        return $this->schemaManager->extractDoctrineTypeFromComment($comment, $currentType);
    }

    public function removeDoctrineTypeFromComment($comment, $type)
    {
        return $this->schemaManager->removeDoctrineTypeFromComment($comment, $type);
    }

    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        return $this->schemaManager->_getPortableTableColumnDefinition($tableColumn);
    }
}
