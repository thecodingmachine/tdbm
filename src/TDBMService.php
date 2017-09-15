<?php

/*
 Copyright (C) 2006-2017 David Négrier - THE CODING MACHINE

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace TheCodingMachine\TDBM;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Mouf\Database\MagicQuery;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use TheCodingMachine\TDBM\QueryFactory\FindObjectsFromSqlQueryFactory;
use TheCodingMachine\TDBM\QueryFactory\FindObjectsQueryFactory;
use TheCodingMachine\TDBM\QueryFactory\FindObjectsFromRawSqlQueryFactory;
use TheCodingMachine\TDBM\Utils\NamingStrategyInterface;
use TheCodingMachine\TDBM\Utils\TDBMDaoGenerator;
use Phlib\Logger\LevelFilter;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * The TDBMService class is the main TDBM class. It provides methods to retrieve TDBMObject instances
 * from the database.
 *
 * @author David Negrier
 * @ExtendedAction {"name":"Generate DAOs", "url":"tdbmadmin/", "default":false}
 */
class TDBMService
{
    const MODE_CURSOR = 1;
    const MODE_ARRAY = 2;

    /**
     * The database connection.
     *
     * @var Connection
     */
    private $connection;

    /**
     * @var SchemaAnalyzer
     */
    private $schemaAnalyzer;

    /**
     * @var MagicQuery
     */
    private $magicQuery;

    /**
     * @var TDBMSchemaAnalyzer
     */
    private $tdbmSchemaAnalyzer;

    /**
     * @var string
     */
    private $cachePrefix;

    /**
     * Cache of table of primary keys.
     * Primary keys are stored by tables, as an array of column.
     * For instance $primary_key['my_table'][0] will return the first column of the primary key of table 'my_table'.
     *
     * @var string[]
     */
    private $primaryKeysColumns;

    /**
     * Service storing objects in memory.
     * Access is done by table name and then by primary key.
     * If the primary key is split on several columns, access is done by an array of columns, serialized.
     *
     * @var StandardObjectStorage|WeakrefObjectStorage
     */
    private $objectStorage;

    /**
     * The fetch mode of the result sets returned by `getObjects`.
     * Can be one of: TDBMObjectArray::MODE_CURSOR or TDBMObjectArray::MODE_ARRAY or TDBMObjectArray::MODE_COMPATIBLE_ARRAY.
     *
     * In 'MODE_ARRAY' mode (default), the result is an array. Use this mode by default (unless the list returned is very big).
     * In 'MODE_CURSOR' mode, the result is a Generator which is an iterable collection that can be scanned only once (only one "foreach") on it,
     * and it cannot be accessed via key. Use this mode for large datasets processed by batch.
     * In 'MODE_COMPATIBLE_ARRAY' mode, the result is an old TDBMObjectArray (used up to TDBM 3.2).
     * You can access the array by key, or using foreach, several times.
     *
     * @var int
     */
    private $mode = self::MODE_ARRAY;

    /**
     * Table of new objects not yet inserted in database or objects modified that must be saved.
     *
     * @var \SplObjectStorage of DbRow objects
     */
    private $toSaveObjects;

    /**
     * A cache service to be used.
     *
     * @var Cache|null
     */
    private $cache;

    /**
     * Map associating a table name to a fully qualified Bean class name.
     *
     * @var array
     */
    private $tableToBeanMap = [];

    /**
     * @var \ReflectionClass[]
     */
    private $reflectionClassCache = array();

    /**
     * @var LoggerInterface
     */
    private $rootLogger;

    /**
     * @var LevelFilter|NullLogger
     */
    private $logger;

    /**
     * @var OrderByAnalyzer
     */
    private $orderByAnalyzer;

    /**
     * @var string
     */
    private $beanNamespace;

    /**
     * @var NamingStrategyInterface
     */
    private $namingStrategy;
    /**
     * @var ConfigurationInterface
     */
    private $configuration;

    /**
     * @param ConfigurationInterface $configuration The configuration object
     */
    public function __construct(ConfigurationInterface $configuration)
    {
        if (extension_loaded('weakref')) {
            $this->objectStorage = new WeakrefObjectStorage();
        } else {
            $this->objectStorage = new StandardObjectStorage();
        }
        $this->connection = $configuration->getConnection();
        $this->cache = $configuration->getCache();
        $this->schemaAnalyzer = $configuration->getSchemaAnalyzer();

        $this->magicQuery = new MagicQuery($this->connection, $this->cache, $this->schemaAnalyzer);

        $this->tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($this->connection, $this->cache, $this->schemaAnalyzer);
        $this->cachePrefix = $this->tdbmSchemaAnalyzer->getCachePrefix();

        $this->toSaveObjects = new \SplObjectStorage();
        $logger = $configuration->getLogger();
        if ($logger === null) {
            $this->logger = new NullLogger();
            $this->rootLogger = new NullLogger();
        } else {
            $this->rootLogger = $logger;
            $this->setLogLevel(LogLevel::WARNING);
        }
        $this->orderByAnalyzer = new OrderByAnalyzer($this->cache, $this->cachePrefix);
        $this->beanNamespace = $configuration->getBeanNamespace();
        $this->namingStrategy = $configuration->getNamingStrategy();
        $this->configuration = $configuration;
    }

    /**
     * Returns the object used to connect to the database.
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Sets the default fetch mode of the result sets returned by `findObjects`.
     * Can be one of: TDBMObjectArray::MODE_CURSOR or TDBMObjectArray::MODE_ARRAY.
     *
     * In 'MODE_ARRAY' mode (default), the result is a ResultIterator object that behaves like an array. Use this mode by default (unless the list returned is very big).
     * In 'MODE_CURSOR' mode, the result is a ResultIterator object. If you scan it many times (by calling several time a foreach loop), the query will be run
     * several times. In cursor mode, you cannot access the result set by key. Use this mode for large datasets processed by batch.
     *
     * @param int $mode
     *
     * @return $this
     *
     * @throws TDBMException
     */
    public function setFetchMode($mode)
    {
        if ($mode !== self::MODE_CURSOR && $mode !== self::MODE_ARRAY) {
            throw new TDBMException("Unknown fetch mode: '".$this->mode."'");
        }
        $this->mode = $mode;

        return $this;
    }

    /**
     * Removes the given object from database.
     * This cannot be called on an object that is not attached to this TDBMService
     * (will throw a TDBMInvalidOperationException).
     *
     * @param AbstractTDBMObject $object the object to delete
     *
     * @throws DBALException
     * @throws TDBMInvalidOperationException
     */
    public function delete(AbstractTDBMObject $object)
    {
        switch ($object->_getStatus()) {
            case TDBMObjectStateEnum::STATE_DELETED:
                // Nothing to do, object already deleted.
                return;
            case TDBMObjectStateEnum::STATE_DETACHED:
                throw new TDBMInvalidOperationException('Cannot delete a detached object');
            case TDBMObjectStateEnum::STATE_NEW:
                $this->deleteManyToManyRelationships($object);
                foreach ($object->_getDbRows() as $dbRow) {
                    $this->removeFromToSaveObjectList($dbRow);
                }
                break;
            case TDBMObjectStateEnum::STATE_DIRTY:
                foreach ($object->_getDbRows() as $dbRow) {
                    $this->removeFromToSaveObjectList($dbRow);
                }
            // And continue deleting...
            case TDBMObjectStateEnum::STATE_NOT_LOADED:
            case TDBMObjectStateEnum::STATE_LOADED:
                $this->connection->beginTransaction();
                try {
                    $this->deleteManyToManyRelationships($object);
                    // Let's delete db rows, in reverse order.
                    foreach (array_reverse($object->_getDbRows()) as $dbRow) {
                        /* @var $dbRow DbRow */
                        $tableName = $dbRow->_getDbTableName();
                        $primaryKeys = $dbRow->_getPrimaryKeys();
                        $quotedPrimaryKeys = [];
                        foreach ($primaryKeys as $column => $value) {
                            $quotedPrimaryKeys[$this->connection->quoteIdentifier($column)] = $value;
                        }
                        $this->connection->delete($this->connection->quoteIdentifier($tableName), $quotedPrimaryKeys);
                        $this->objectStorage->remove($dbRow->_getDbTableName(), $this->getObjectHash($primaryKeys));
                    }
                    $this->connection->commit();
                } catch (DBALException $e) {
                    $this->connection->rollBack();
                    throw $e;
                }
                break;
            // @codeCoverageIgnoreStart
            default:
                throw new TDBMInvalidOperationException('Unexpected status for bean');
            // @codeCoverageIgnoreEnd
        }

        $object->_setStatus(TDBMObjectStateEnum::STATE_DELETED);
    }

    /**
     * Removes all many to many relationships for this object.
     *
     * @param AbstractTDBMObject $object
     */
    private function deleteManyToManyRelationships(AbstractTDBMObject $object)
    {
        foreach ($object->_getDbRows() as $tableName => $dbRow) {
            $pivotTables = $this->tdbmSchemaAnalyzer->getPivotTableLinkedToTable($tableName);
            foreach ($pivotTables as $pivotTable) {
                $remoteBeans = $object->_getRelationships($pivotTable);
                foreach ($remoteBeans as $remoteBean) {
                    $object->_removeRelationship($pivotTable, $remoteBean);
                }
            }
        }
        $this->persistManyToManyRelationships($object);
    }

    /**
     * This function removes the given object from the database. It will also remove all objects relied to the one given
     * by parameter before all.
     *
     * Notice: if the object has a multiple primary key, the function will not work.
     *
     * @param AbstractTDBMObject $objToDelete
     */
    public function deleteCascade(AbstractTDBMObject $objToDelete)
    {
        $this->deleteAllConstraintWithThisObject($objToDelete);
        $this->delete($objToDelete);
    }

    /**
     * This function is used only in TDBMService (private function)
     * It will call deleteCascade function foreach object relied with a foreign key to the object given by parameter.
     *
     * @param AbstractTDBMObject $obj
     */
    private function deleteAllConstraintWithThisObject(AbstractTDBMObject $obj)
    {
        $dbRows = $obj->_getDbRows();
        foreach ($dbRows as $dbRow) {
            $tableName = $dbRow->_getDbTableName();
            $pks = array_values($dbRow->_getPrimaryKeys());
            if (!empty($pks)) {
                $incomingFks = $this->tdbmSchemaAnalyzer->getIncomingForeignKeys($tableName);

                foreach ($incomingFks as $incomingFk) {
                    $filter = array_combine($incomingFk->getUnquotedLocalColumns(), $pks);

                    $results = $this->findObjects($incomingFk->getLocalTableName(), $filter);

                    foreach ($results as $bean) {
                        $this->deleteCascade($bean);
                    }
                }
            }
        }
    }

    /**
     * This function performs a save() of all the objects that have been modified.
     */
    public function completeSave()
    {
        foreach ($this->toSaveObjects as $dbRow) {
            $this->save($dbRow->getTDBMObject());
        }
    }

    /**
     * Takes in input a filter_bag (which can be about anything from a string to an array of TDBMObjects... see above from documentation),
     * and gives back a proper Filter object.
     *
     * @param mixed $filter_bag
     * @param AbstractPlatform $platform The platform used to quote identifiers
     * @param int $counter
     * @return array First item: filter string, second item: parameters
     *
     * @throws TDBMException
     */
    public function buildFilterFromFilterBag($filter_bag, AbstractPlatform $platform, $counter = 1)
    {
        if ($filter_bag === null) {
            return ['', []];
        } elseif (is_string($filter_bag)) {
            return [$filter_bag, []];
        } elseif (is_array($filter_bag)) {
            $sqlParts = [];
            $parameters = [];

            foreach ($filter_bag as $column => $value) {
                if (is_int($column)) {
                    list($subSqlPart, $subParameters) = $this->buildFilterFromFilterBag($value, $platform, $counter);
                    $sqlParts[] = $subSqlPart;
                    $parameters += $subParameters;
                } else {
                    $paramName = 'tdbmparam'.$counter;
                    if (is_array($value)) {
                        $sqlParts[] = $platform->quoteIdentifier($column).' IN :'.$paramName;
                    } else {
                        $sqlParts[] = $platform->quoteIdentifier($column).' = :'.$paramName;
                    }
                    $parameters[$paramName] = $value;
                    ++$counter;
                }
            }

            return [implode(' AND ', $sqlParts), $parameters];
        } elseif ($filter_bag instanceof AbstractTDBMObject) {
            $sqlParts = [];
            $parameters = [];
            $dbRows = $filter_bag->_getDbRows();
            $dbRow = reset($dbRows);
            $primaryKeys = $dbRow->_getPrimaryKeys();

            foreach ($primaryKeys as $column => $value) {
                $paramName = 'tdbmparam'.$counter;
                $sqlParts[] = $platform->quoteIdentifier($dbRow->_getDbTableName()).'.'.$platform->quoteIdentifier($column).' = :'.$paramName;
                $parameters[$paramName] = $value;
                ++$counter;
            }

            return [implode(' AND ', $sqlParts), $parameters];
        } elseif ($filter_bag instanceof \Iterator) {
            return $this->buildFilterFromFilterBag(iterator_to_array($filter_bag), $platform, $counter);
        } else {
            throw new TDBMException('Error in filter. An object has been passed that is neither a SQL string, nor an array, nor a bean, nor null.');
        }
    }

    /**
     * @param string $table
     *
     * @return string[]
     */
    public function getPrimaryKeyColumns($table)
    {
        if (!isset($this->primaryKeysColumns[$table])) {
            $this->primaryKeysColumns[$table] = $this->tdbmSchemaAnalyzer->getSchema()->getTable($table)->getPrimaryKey()->getUnquotedColumns();

            // TODO TDBM4: See if we need to improve error reporting if table name does not exist.

            /*$arr = array();
            foreach ($this->connection->getPrimaryKey($table) as $col) {
                $arr[] = $col->name;
            }
            // The primaryKeysColumns contains only the column's name, not the DB_Column object.
            $this->primaryKeysColumns[$table] = $arr;
            if (empty($this->primaryKeysColumns[$table]))
            {
                // Unable to find primary key.... this is an error
                // Let's try to be precise in error reporting. Let's try to find the table.
                $tables = $this->connection->checkTableExist($table);
                if ($tables === true)
                throw new TDBMException("Could not find table primary key for table '$table'. Please define a primary key for this table.");
                elseif ($tables !== null) {
                    if (count($tables)==1)
                    $str = "Could not find table '$table'. Maybe you meant this table: '".$tables[0]."'";
                    else
                    $str = "Could not find table '$table'. Maybe you meant one of those tables: '".implode("', '",$tables)."'";
                    throw new TDBMException($str);
                }
            }*/
        }

        return $this->primaryKeysColumns[$table];
    }

    /**
     * This is an internal function, you should not use it in your application.
     * This is used internally by TDBM to add an object to the object cache.
     *
     * @param DbRow $dbRow
     */
    public function _addToCache(DbRow $dbRow)
    {
        $primaryKey = $this->getPrimaryKeysForObjectFromDbRow($dbRow);
        $hash = $this->getObjectHash($primaryKey);
        $this->objectStorage->set($dbRow->_getDbTableName(), $hash, $dbRow);
    }

    /**
     * This is an internal function, you should not use it in your application.
     * This is used internally by TDBM to remove the object from the list of objects that have been
     * created/updated but not saved yet.
     *
     * @param DbRow $myObject
     */
    private function removeFromToSaveObjectList(DbRow $myObject)
    {
        unset($this->toSaveObjects[$myObject]);
    }

    /**
     * This is an internal function, you should not use it in your application.
     * This is used internally by TDBM to add an object to the list of objects that have been
     * created/updated but not saved yet.
     *
     * @param DbRow $myObject
     */
    public function _addToToSaveObjectList(DbRow $myObject)
    {
        $this->toSaveObjects[$myObject] = true;
    }

    /**
     * Generates all the daos and beans.
     *
     * @return \string[] the list of tables (key) and bean name (value)
     */
    public function generateAllDaosAndBeans()
    {
        // Purge cache before generating anything.
        $this->cache->deleteAll();

        $tdbmDaoGenerator = new TDBMDaoGenerator($this->configuration, $this->tdbmSchemaAnalyzer);
        $tdbmDaoGenerator->generateAllDaosAndBeans();
    }

    /**
     * Returns the fully qualified class name of the bean associated with table $tableName.
     *
     *
     * @param string $tableName
     *
     * @return string
     */
    public function getBeanClassName(string $tableName) : string
    {
        if (isset($this->tableToBeanMap[$tableName])) {
            return $this->tableToBeanMap[$tableName];
        } else {
            $className = $this->beanNamespace.'\\'.$this->namingStrategy->getBeanClassName($tableName);

            if (!class_exists($className)) {
                throw new TDBMInvalidArgumentException(sprintf('Could not find class "%s". Does table "%s" exist? If yes, consider regenerating the DAOs and beans.', $className, $tableName));
            }

            $this->tableToBeanMap[$tableName] = $className;
            return $className;
        }
    }

    /**
     * Saves $object by INSERTing or UPDAT(E)ing it in the database.
     *
     * @param AbstractTDBMObject $object
     *
     * @throws TDBMException
     */
    public function save(AbstractTDBMObject $object)
    {
        $status = $object->_getStatus();

        if ($status === null) {
            throw new TDBMException(sprintf('Your bean for class %s has no status. It is likely that you overloaded the __construct method and forgot to call parent::__construct.', get_class($object)));
        }

        // Let's attach this object if it is in detached state.
        if ($status === TDBMObjectStateEnum::STATE_DETACHED) {
            $object->_attach($this);
            $status = $object->_getStatus();
        }

        if ($status === TDBMObjectStateEnum::STATE_NEW) {
            $dbRows = $object->_getDbRows();

            $unindexedPrimaryKeys = array();

            foreach ($dbRows as $dbRow) {
                if ($dbRow->_getStatus() == TDBMObjectStateEnum::STATE_SAVING) {
                    throw TDBMCyclicReferenceException::createCyclicReference($dbRow->_getDbTableName(), $object);
                }
                $dbRow->_setStatus(TDBMObjectStateEnum::STATE_SAVING);
                $tableName = $dbRow->_getDbTableName();

                $schema = $this->tdbmSchemaAnalyzer->getSchema();
                $tableDescriptor = $schema->getTable($tableName);

                $primaryKeyColumns = $this->getPrimaryKeyColumns($tableName);

                $references = $dbRow->_getReferences();

                // Let's save all references in NEW or DETACHED state (we need their primary key)
                foreach ($references as $fkName => $reference) {
                    if ($reference !== null) {
                        $refStatus = $reference->_getStatus();
                        if ($refStatus === TDBMObjectStateEnum::STATE_NEW || $refStatus === TDBMObjectStateEnum::STATE_DETACHED) {
                            try {
                                $this->save($reference);
                            } catch (TDBMCyclicReferenceException $e) {
                                throw TDBMCyclicReferenceException::extendCyclicReference($e, $dbRow->_getDbTableName(), $object, $fkName);
                            }
                        }
                    }
                }

                if (empty($unindexedPrimaryKeys)) {
                    $primaryKeys = $this->getPrimaryKeysForObjectFromDbRow($dbRow);
                } else {
                    // First insert, the children must have the same primary key as the parent.
                    $primaryKeys = $this->_getPrimaryKeysFromIndexedPrimaryKeys($tableName, $unindexedPrimaryKeys);
                    $dbRow->_setPrimaryKeys($primaryKeys);
                }

                $dbRowData = $dbRow->_getDbRow();

                // Let's see if the columns for primary key have been set before inserting.
                // We assume that if one of the value of the PK has been set, the PK is set.
                $isPkSet = !empty($primaryKeys);

                /*if (!$isPkSet) {
                    // if there is no autoincrement and no pk set, let's go in error.
                    $isAutoIncrement = true;

                    foreach ($primaryKeyColumns as $pkColumnName) {
                        $pkColumn = $tableDescriptor->getColumn($pkColumnName);
                        if (!$pkColumn->getAutoincrement()) {
                            $isAutoIncrement = false;
                        }
                    }

                    if (!$isAutoIncrement) {
                        $msg = "Error! You did not set the primary key(s) for the new object of type '$tableName'. The primary key is not set to 'autoincrement' so you must either set the primary key in the object or modify the DB model to create an primary key with auto-increment.";
                        throw new TDBMException($msg);
                    }

                }*/

                $types = [];
                $escapedDbRowData = [];

                foreach ($dbRowData as $columnName => $value) {
                    $columnDescriptor = $tableDescriptor->getColumn($columnName);
                    $types[] = $columnDescriptor->getType();
                    $escapedDbRowData[$this->connection->quoteIdentifier($columnName)] = $value;
                }

                $quotedTableName = $this->connection->quoteIdentifier($tableName);
                $this->connection->insert($quotedTableName, $escapedDbRowData, $types);

                if (!$isPkSet && count($primaryKeyColumns) === 1) {
                    $id = $this->connection->lastInsertId();

                    if ($id === false) {
                        // In Oracle (if we are in 11g), the lastInsertId will fail. We try again with the column.
                        $sequenceName = $this->connection->getDatabasePlatform()->getIdentitySequenceName(
                            $quotedTableName,
                            $this->connection->quoteIdentifier($primaryKeyColumns[0])
                        );
                        $id = $this->connection->lastInsertId($sequenceName);
                    }

                    $pkColumn = $primaryKeyColumns[0];
                    // lastInsertId returns a string but the column type is usually a int. Let's convert it back to the correct type.
                    $id = $tableDescriptor->getColumn($pkColumn)->getType()->convertToPHPValue($id, $this->getConnection()->getDatabasePlatform());
                    $primaryKeys[$pkColumn] = $id;
                }

                // TODO: change this to some private magic accessor in future
                $dbRow->_setPrimaryKeys($primaryKeys);
                $unindexedPrimaryKeys = array_values($primaryKeys);

                /*
                 * When attached, on "save", we check if the column updated is part of a primary key
                 * If this is part of a primary key, we call the _update_id method that updates the id in the list of known objects.
                 * This method should first verify that the id is not already used (and is not auto-incremented)
                 *
                 * In the object, the key is stored in an array of  (column => value), that can be directly used to update the record.
                 *
                 *
                 */

                /*try {
                    $this->db_connection->exec($sql);
                } catch (TDBMException $e) {
                    $this->db_onerror = true;

                    // Strange..... if we do not have the line below, bad inserts are not catched.
                    // It seems that destructors are called before the registered shutdown function (PHP >=5.0.5)
                    //if ($this->tdbmService->isProgramExiting())
                    //	trigger_error("program exiting");
                    trigger_error($e->getMessage(), E_USER_ERROR);

                    if (!$this->tdbmService->isProgramExiting())
                        throw $e;
                    else
                    {
                        trigger_error($e->getMessage(), E_USER_ERROR);
                    }
                }*/

                // Let's remove this object from the $new_objects static table.
                $this->removeFromToSaveObjectList($dbRow);

                // TODO: change this behaviour to something more sensible performance-wise
                // Maybe a setting to trigger this globally?
                //$this->status = TDBMObjectStateEnum::STATE_NOT_LOADED;
                //$this->db_modified_state = false;
                //$dbRow = array();

                // Let's add this object to the list of objects in cache.
                $this->_addToCache($dbRow);
            }

            $object->_setStatus(TDBMObjectStateEnum::STATE_LOADED);
        } elseif ($status === TDBMObjectStateEnum::STATE_DIRTY) {
            $dbRows = $object->_getDbRows();

            foreach ($dbRows as $dbRow) {
                $references = $dbRow->_getReferences();

                // Let's save all references in NEW state (we need their primary key)
                foreach ($references as $fkName => $reference) {
                    if ($reference !== null && $reference->_getStatus() === TDBMObjectStateEnum::STATE_NEW) {
                        $this->save($reference);
                    }
                }

                // Let's first get the primary keys
                $tableName = $dbRow->_getDbTableName();
                $dbRowData = $dbRow->_getDbRow();

                $schema = $this->tdbmSchemaAnalyzer->getSchema();
                $tableDescriptor = $schema->getTable($tableName);

                $primaryKeys = $dbRow->_getPrimaryKeys();

                $types = [];
                $escapedDbRowData = [];
                $escapedPrimaryKeys = [];

                foreach ($dbRowData as $columnName => $value) {
                    $columnDescriptor = $tableDescriptor->getColumn($columnName);
                    $types[] = $columnDescriptor->getType();
                    $escapedDbRowData[$this->connection->quoteIdentifier($columnName)] = $value;
                }
                foreach ($primaryKeys as $columnName => $value) {
                    $columnDescriptor = $tableDescriptor->getColumn($columnName);
                    $types[] = $columnDescriptor->getType();
                    $escapedPrimaryKeys[$this->connection->quoteIdentifier($columnName)] = $value;
                }

                $this->connection->update($this->connection->quoteIdentifier($tableName), $escapedDbRowData, $escapedPrimaryKeys, $types);

                // Let's check if the primary key has been updated...
                $needsUpdatePk = false;
                foreach ($primaryKeys as $column => $value) {
                    if (!isset($dbRowData[$column]) || $dbRowData[$column] != $value) {
                        $needsUpdatePk = true;
                        break;
                    }
                }
                if ($needsUpdatePk) {
                    $this->objectStorage->remove($tableName, $this->getObjectHash($primaryKeys));
                    $newPrimaryKeys = $this->getPrimaryKeysForObjectFromDbRow($dbRow);
                    $dbRow->_setPrimaryKeys($newPrimaryKeys);
                    $this->objectStorage->set($tableName, $this->getObjectHash($primaryKeys), $dbRow);
                }

                // Let's remove this object from the list of objects to save.
                $this->removeFromToSaveObjectList($dbRow);
            }

            $object->_setStatus(TDBMObjectStateEnum::STATE_LOADED);
        } elseif ($status === TDBMObjectStateEnum::STATE_DELETED) {
            throw new TDBMInvalidOperationException('This object has been deleted. It cannot be saved.');
        }

        // Finally, let's save all the many to many relationships to this bean.
        $this->persistManyToManyRelationships($object);
    }

    private function persistManyToManyRelationships(AbstractTDBMObject $object)
    {
        foreach ($object->_getCachedRelationships() as $pivotTableName => $storage) {
            $tableDescriptor = $this->tdbmSchemaAnalyzer->getSchema()->getTable($pivotTableName);
            list($localFk, $remoteFk) = $this->getPivotTableForeignKeys($pivotTableName, $object);

            $toRemoveFromStorage = [];

            foreach ($storage as $remoteBean) {
                /* @var $remoteBean AbstractTDBMObject */
                $statusArr = $storage[$remoteBean];
                $status = $statusArr['status'];
                $reverse = $statusArr['reverse'];
                if ($reverse) {
                    continue;
                }

                if ($status === 'new') {
                    $remoteBeanStatus = $remoteBean->_getStatus();
                    if ($remoteBeanStatus === TDBMObjectStateEnum::STATE_NEW || $remoteBeanStatus === TDBMObjectStateEnum::STATE_DETACHED) {
                        // Let's save remote bean if needed.
                        $this->save($remoteBean);
                    }

                    ['filters' => $filters, 'types' => $types] = $this->getPivotFilters($object, $remoteBean, $localFk, $remoteFk, $tableDescriptor);

                    $this->connection->insert($this->connection->quoteIdentifier($pivotTableName), $filters, $types);

                    // Finally, let's mark relationships as saved.
                    $statusArr['status'] = 'loaded';
                    $storage[$remoteBean] = $statusArr;
                    $remoteStorage = $remoteBean->_getCachedRelationships()[$pivotTableName];
                    $remoteStatusArr = $remoteStorage[$object];
                    $remoteStatusArr['status'] = 'loaded';
                    $remoteStorage[$object] = $remoteStatusArr;
                } elseif ($status === 'delete') {
                    ['filters' => $filters, 'types' => $types] = $this->getPivotFilters($object, $remoteBean, $localFk, $remoteFk, $tableDescriptor);

                    $this->connection->delete($this->connection->quoteIdentifier($pivotTableName), $filters, $types);

                    // Finally, let's remove relationships completely from bean.
                    $toRemoveFromStorage[] = $remoteBean;

                    $remoteBean->_getCachedRelationships()[$pivotTableName]->detach($object);
                }
            }

            // Note: due to https://bugs.php.net/bug.php?id=65629, we cannot delete an element inside a foreach loop on a SplStorageObject.
            // Therefore, we cache elements in the $toRemoveFromStorage to remove them at a later stage.
            foreach ($toRemoveFromStorage as $remoteBean) {
                $storage->detach($remoteBean);
            }
        }
    }

    private function getPivotFilters(AbstractTDBMObject $localBean, AbstractTDBMObject $remoteBean, ForeignKeyConstraint $localFk, ForeignKeyConstraint $remoteFk, Table $tableDescriptor)
    {
        $localBeanPk = $this->getPrimaryKeyValues($localBean);
        $remoteBeanPk = $this->getPrimaryKeyValues($remoteBean);
        $localColumns = $localFk->getUnquotedLocalColumns();
        $remoteColumns = $remoteFk->getUnquotedLocalColumns();

        $localFilters = array_combine($localColumns, $localBeanPk);
        $remoteFilters = array_combine($remoteColumns, $remoteBeanPk);

        $filters = array_merge($localFilters, $remoteFilters);

        $types = [];
        $escapedFilters = [];

        foreach ($filters as $columnName => $value) {
            $columnDescriptor = $tableDescriptor->getColumn($columnName);
            $types[] = $columnDescriptor->getType();
            $escapedFilters[$this->connection->quoteIdentifier($columnName)] = $value;
        }
        return ['filters' => $escapedFilters, 'types' => $types];
    }

    /**
     * Returns the "values" of the primary key.
     * This returns the primary key from the $primaryKey attribute, not the one stored in the columns.
     *
     * @param AbstractTDBMObject $bean
     *
     * @return array numerically indexed array of values
     */
    private function getPrimaryKeyValues(AbstractTDBMObject $bean)
    {
        $dbRows = $bean->_getDbRows();
        $dbRow = reset($dbRows);

        return array_values($dbRow->_getPrimaryKeys());
    }

    /**
     * Returns a unique hash used to store the object based on its primary key.
     * If the array contains only one value, then the value is returned.
     * Otherwise, a hash representing the array is returned.
     *
     * @param array $primaryKeys An array of columns => values forming the primary key
     *
     * @return string
     */
    public function getObjectHash(array $primaryKeys)
    {
        if (count($primaryKeys) === 1) {
            return reset($primaryKeys);
        } else {
            ksort($primaryKeys);

            return md5(json_encode($primaryKeys));
        }
    }

    /**
     * Returns an array of primary keys from the object.
     * The primary keys are extracted from the object columns and not from the primary keys stored in the
     * $primaryKeys variable of the object.
     *
     * @param DbRow $dbRow
     *
     * @return array Returns an array of column => value
     */
    public function getPrimaryKeysForObjectFromDbRow(DbRow $dbRow)
    {
        $table = $dbRow->_getDbTableName();
        $dbRowData = $dbRow->_getDbRow();

        return $this->_getPrimaryKeysFromObjectData($table, $dbRowData);
    }

    /**
     * Returns an array of primary keys for the given row.
     * The primary keys are extracted from the object columns.
     *
     * @param $table
     * @param array $columns
     *
     * @return array
     */
    public function _getPrimaryKeysFromObjectData($table, array $columns)
    {
        $primaryKeyColumns = $this->getPrimaryKeyColumns($table);
        $values = array();
        foreach ($primaryKeyColumns as $column) {
            if (isset($columns[$column])) {
                $values[$column] = $columns[$column];
            }
        }

        return $values;
    }

    /**
     * Attaches $object to this TDBMService.
     * The $object must be in DETACHED state and will pass in NEW state.
     *
     * @param AbstractTDBMObject $object
     *
     * @throws TDBMInvalidOperationException
     */
    public function attach(AbstractTDBMObject $object)
    {
        $object->_attach($this);
    }

    /**
     * Returns an associative array (column => value) for the primary keys from the table name and an
     * indexed array of primary key values.
     *
     * @param string $tableName
     * @param array  $indexedPrimaryKeys
     */
    public function _getPrimaryKeysFromIndexedPrimaryKeys($tableName, array $indexedPrimaryKeys)
    {
        $primaryKeyColumns = $this->tdbmSchemaAnalyzer->getSchema()->getTable($tableName)->getPrimaryKey()->getUnquotedColumns();

        if (count($primaryKeyColumns) !== count($indexedPrimaryKeys)) {
            throw new TDBMException(sprintf('Wrong number of columns passed for primary key. Expected %s columns for table "%s",
			got %s instead.', count($primaryKeyColumns), $tableName, count($indexedPrimaryKeys)));
        }

        return array_combine($primaryKeyColumns, $indexedPrimaryKeys);
    }

    /**
     * Return the list of tables (from child to parent) joining the tables passed in parameter.
     * Tables must be in a single line of inheritance. The method will find missing tables.
     *
     * Algorithm: one of those tables is the ultimate child. From this child, by recursively getting the parent,
     * we must be able to find all other tables.
     *
     * @param string[] $tables
     *
     * @return string[]
     */
    public function _getLinkBetweenInheritedTables(array $tables)
    {
        sort($tables);

        return $this->fromCache($this->cachePrefix.'_linkbetweeninheritedtables_'.implode('__split__', $tables),
            function () use ($tables) {
                return $this->_getLinkBetweenInheritedTablesWithoutCache($tables);
            });
    }

    /**
     * Return the list of tables (from child to parent) joining the tables passed in parameter.
     * Tables must be in a single line of inheritance. The method will find missing tables.
     *
     * Algorithm: one of those tables is the ultimate child. From this child, by recursively getting the parent,
     * we must be able to find all other tables.
     *
     * @param string[] $tables
     *
     * @return string[]
     */
    private function _getLinkBetweenInheritedTablesWithoutCache(array $tables)
    {
        $schemaAnalyzer = $this->schemaAnalyzer;

        foreach ($tables as $currentTable) {
            $allParents = [$currentTable];
            while ($currentFk = $schemaAnalyzer->getParentRelationship($currentTable)) {
                $currentTable = $currentFk->getForeignTableName();
                $allParents[] = $currentTable;
            }

            // Now, does the $allParents contain all the tables we want?
            $notFoundTables = array_diff($tables, $allParents);
            if (empty($notFoundTables)) {
                // We have a winner!
                return $allParents;
            }
        }

        throw TDBMInheritanceException::create($tables);
    }

    /**
     * Returns the list of tables related to this table (via a parent or child inheritance relationship).
     *
     * @param string $table
     *
     * @return string[]
     */
    public function _getRelatedTablesByInheritance($table)
    {
        return $this->fromCache($this->cachePrefix.'_relatedtables_'.$table, function () use ($table) {
            return $this->_getRelatedTablesByInheritanceWithoutCache($table);
        });
    }

    /**
     * Returns the list of tables related to this table (via a parent or child inheritance relationship).
     *
     * @param string $table
     *
     * @return string[]
     */
    private function _getRelatedTablesByInheritanceWithoutCache($table)
    {
        $schemaAnalyzer = $this->schemaAnalyzer;

        // Let's scan the parent tables
        $currentTable = $table;

        $parentTables = [];

        // Get parent relationship
        while ($currentFk = $schemaAnalyzer->getParentRelationship($currentTable)) {
            $currentTable = $currentFk->getForeignTableName();
            $parentTables[] = $currentTable;
        }

        // Let's recurse in children
        $childrenTables = $this->exploreChildrenTablesRelationships($schemaAnalyzer, $table);

        return array_merge(array_reverse($parentTables), $childrenTables);
    }

    /**
     * Explore all the children and descendant of $table and returns ForeignKeyConstraints on those.
     *
     * @param string $table
     *
     * @return string[]
     */
    private function exploreChildrenTablesRelationships(SchemaAnalyzer $schemaAnalyzer, $table)
    {
        $tables = [$table];
        $keys = $schemaAnalyzer->getChildrenRelationships($table);

        foreach ($keys as $key) {
            $tables = array_merge($tables, $this->exploreChildrenTablesRelationships($schemaAnalyzer, $key->getLocalTableName()));
        }

        return $tables;
    }

    /**
     * Casts a foreign key into SQL, assuming table name is used with no alias.
     * The returned value does contain only one table. For instance:.
     *
     * " LEFT JOIN table2 ON table1.id = table2.table1_id"
     *
     * @param ForeignKeyConstraint $fk
     * @param bool                 $leftTableIsLocal
     *
     * @return string
     */
    /*private function foreignKeyToSql(ForeignKeyConstraint $fk, $leftTableIsLocal) {
        $onClauses = [];
        $foreignTableName = $this->connection->quoteIdentifier($fk->getForeignTableName());
        $foreignColumns = $fk->getUnquotedForeignColumns();
        $localTableName = $this->connection->quoteIdentifier($fk->getLocalTableName());
        $localColumns = $fk->getUnquotedLocalColumns();
        $columnCount = count($localTableName);

        for ($i = 0; $i < $columnCount; $i++) {
            $onClauses[] = sprintf("%s.%s = %s.%s",
                $localTableName,
                $this->connection->quoteIdentifier($localColumns[$i]),
                $foreignColumns,
                $this->connection->quoteIdentifier($foreignColumns[$i])
                );
        }

        $onClause = implode(' AND ', $onClauses);

        if ($leftTableIsLocal) {
            return sprintf(" LEFT JOIN %s ON (%s)", $foreignTableName, $onClause);
        } else {
            return sprintf(" LEFT JOIN %s ON (%s)", $localTableName, $onClause);
        }
    }*/

    /**
     * Returns a `ResultIterator` object representing filtered records of "$mainTable" .
     *
     * The findObjects method should be the most used query method in TDBM if you want to query the database for objects.
     * (Note: if you want to query the database for an object by its primary key, use the findObjectByPk method).
     *
     * The findObjects method takes in parameter:
     * 	- mainTable: the kind of bean you want to retrieve. In TDBM, a bean matches a database row, so the
     * 			`$mainTable` parameter should be the name of an existing table in database.
     *  - filter: The filter is a filter bag. It is what you use to filter your request (the WHERE part in SQL).
     *          It can be a string (SQL Where clause), or even a bean or an associative array (key = column to filter, value = value to find)
     *  - parameters: The parameters used in the filter. If you pass a SQL string as a filter, be sure to avoid
     *          concatenating parameters in the string (this leads to SQL injection and also to poor caching performance).
     *          Instead, please consider passing parameters (see documentation for more details).
     *  - additionalTablesFetch: An array of SQL tables names. The beans related to those tables will be fetched along
     *          the main table. This is useful to avoid hitting the database with numerous subqueries.
     *  - mode: The fetch mode of the result. See `setFetchMode()` method for more details.
     *
     * The `findObjects` method will return a `ResultIterator`. A `ResultIterator` is an object that behaves as an array
     * (in ARRAY mode) at least. It can be iterated using a `foreach` loop.
     *
     * Finally, if filter_bag is null, the whole table is returned.
     *
     * @param string                       $mainTable             The name of the table queried
     * @param string|array|null            $filter                The SQL filters to apply to the query (the WHERE part). Columns from tables different from $mainTable must be prefixed by the table name (in the form: table.column)
     * @param array                        $parameters
     * @param string|UncheckedOrderBy|null $orderString           The ORDER BY part of the query. Columns from tables different from $mainTable must be prefixed by the table name (in the form: table.column)
     * @param array                        $additionalTablesFetch
     * @param int                          $mode
     * @param string                       $className             Optional: The name of the class to instantiate. This class must extend the TDBMObject class. If none is specified, a TDBMObject instance will be returned
     *
     * @return ResultIterator An object representing an array of results
     *
     * @throws TDBMException
     */
    public function findObjects(string $mainTable, $filter = null, array $parameters = array(), $orderString = null, array $additionalTablesFetch = array(), $mode = null, string $className = null)
    {
        // $mainTable is not secured in MagicJoin, let's add a bit of security to avoid SQL injection.
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $mainTable)) {
            throw new TDBMException(sprintf("Invalid table name: '%s'", $mainTable));
        }

        $mode = $mode ?: $this->mode;

        // We quote in MySQL because MagicJoin requires MySQL style quotes
        $mysqlPlatform = new MySqlPlatform();
        list($filterString, $additionalParameters) = $this->buildFilterFromFilterBag($filter, $mysqlPlatform);

        $parameters = array_merge($parameters, $additionalParameters);

        $queryFactory = new FindObjectsQueryFactory($mainTable, $additionalTablesFetch, $filterString, $orderString, $this, $this->tdbmSchemaAnalyzer->getSchema(), $this->orderByAnalyzer);

        return new ResultIterator($queryFactory, $parameters, $this->objectStorage, $className, $this, $this->magicQuery, $mode, $this->logger);
    }

    /**
     * @param string                       $mainTable   The name of the table queried
     * @param string                       $from        The from sql statement
     * @param string|array|null            $filter      The SQL filters to apply to the query (the WHERE part). All columns must be prefixed by the table name (in the form: table.column)
     * @param array                        $parameters
     * @param string|UncheckedOrderBy|null $orderString The ORDER BY part of the query. All columns must be prefixed by the table name (in the form: table.column)
     * @param int                          $mode
     * @param string                       $className   Optional: The name of the class to instantiate. This class must extend the TDBMObject class. If none is specified, a TDBMObject instance will be returned
     *
     * @return ResultIterator An object representing an array of results
     *
     * @throws TDBMException
     */
    public function findObjectsFromSql(string $mainTable, string $from, $filter = null, array $parameters = array(), $orderString = null, $mode = null, string $className = null)
    {
        // $mainTable is not secured in MagicJoin, let's add a bit of security to avoid SQL injection.
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $mainTable)) {
            throw new TDBMException(sprintf("Invalid table name: '%s'", $mainTable));
        }

        $mode = $mode ?: $this->mode;

        // We quote in MySQL because MagicJoin requires MySQL style quotes
        $mysqlPlatform = new MySqlPlatform();
        list($filterString, $additionalParameters) = $this->buildFilterFromFilterBag($filter, $mysqlPlatform);

        $parameters = array_merge($parameters, $additionalParameters);

        $queryFactory = new FindObjectsFromSqlQueryFactory($mainTable, $from, $filterString, $orderString, $this, $this->tdbmSchemaAnalyzer->getSchema(), $this->orderByAnalyzer, $this->schemaAnalyzer, $this->cache, $this->cachePrefix);

        return new ResultIterator($queryFactory, $parameters, $this->objectStorage, $className, $this, $this->magicQuery, $mode, $this->logger);
    }

    /**
     * @param $table
     * @param array  $primaryKeys
     * @param array  $additionalTablesFetch
     * @param bool   $lazy                  Whether to perform lazy loading on this object or not
     * @param string $className
     *
     * @return AbstractTDBMObject
     *
     * @throws TDBMException
     */
    public function findObjectByPk(string $table, array $primaryKeys, array $additionalTablesFetch = array(), bool $lazy = false, string $className = null)
    {
        $primaryKeys = $this->_getPrimaryKeysFromObjectData($table, $primaryKeys);
        $hash = $this->getObjectHash($primaryKeys);

        if ($this->objectStorage->has($table, $hash)) {
            $dbRow = $this->objectStorage->get($table, $hash);
            $bean = $dbRow->getTDBMObject();
            if ($className !== null && !is_a($bean, $className)) {
                throw new TDBMException("TDBM cannot create a bean of class '".$className."'. The requested object was already loaded and its class is '".get_class($bean)."'");
            }

            return $bean;
        }

        // Are we performing lazy fetching?
        if ($lazy === true) {
            // Can we perform lazy fetching?
            $tables = $this->_getRelatedTablesByInheritance($table);
            // Only allowed if no inheritance.
            if (count($tables) === 1) {
                if ($className === null) {
                    try {
                        $className = $this->getBeanClassName($table);
                    } catch (TDBMInvalidArgumentException $e) {
                        $className = TDBMObject::class;
                    }
                }

                // Let's construct the bean
                if (!isset($this->reflectionClassCache[$className])) {
                    $this->reflectionClassCache[$className] = new \ReflectionClass($className);
                }
                // Let's bypass the constructor when creating the bean!
                $bean = $this->reflectionClassCache[$className]->newInstanceWithoutConstructor();
                /* @var $bean AbstractTDBMObject */
                $bean->_constructLazy($table, $primaryKeys, $this);

                return $bean;
            }
        }

        // Did not find the object in cache? Let's query it!
        return $this->findObjectOrFail($table, $primaryKeys, [], $additionalTablesFetch, $className);
    }

    /**
     * Returns a unique bean (or null) according to the filters passed in parameter.
     *
     * @param string            $mainTable             The name of the table queried
     * @param string|array|null $filter                The SQL filters to apply to the query (the WHERE part). All columns must be prefixed by the table name (in the form: table.column)
     * @param array             $parameters
     * @param array             $additionalTablesFetch
     * @param string            $className             Optional: The name of the class to instantiate. This class must extend the TDBMObject class. If none is specified, a TDBMObject instance will be returned
     *
     * @return AbstractTDBMObject|null The object we want, or null if no object matches the filters
     *
     * @throws TDBMException
     */
    public function findObject(string $mainTable, $filter = null, array $parameters = array(), array $additionalTablesFetch = array(), string $className = null)
    {
        $objects = $this->findObjects($mainTable, $filter, $parameters, null, $additionalTablesFetch, self::MODE_ARRAY, $className);
        $page = $objects->take(0, 2);


        $pageArr = $page->toArray();
        // Optimisation: the $page->count() query can trigger an additional SQL query in platforms other than MySQL.
        // We try to avoid calling at by fetching all 2 columns instead.
        $count = count($pageArr);

        if ($count > 1) {
            throw new DuplicateRowException("Error while querying an object for table '$mainTable': More than 1 row have been returned, but we should have received at most one.");
        } elseif ($count === 0) {
            return;
        }

        return $pageArr[0];
    }

    /**
     * Returns a unique bean (or null) according to the filters passed in parameter.
     *
     * @param string            $mainTable  The name of the table queried
     * @param string            $from       The from sql statement
     * @param string|array|null $filter     The SQL filters to apply to the query (the WHERE part). All columns must be prefixed by the table name (in the form: table.column)
     * @param array             $parameters
     * @param string            $className  Optional: The name of the class to instantiate. This class must extend the TDBMObject class. If none is specified, a TDBMObject instance will be returned
     *
     * @return AbstractTDBMObject|null The object we want, or null if no object matches the filters
     *
     * @throws TDBMException
     */
    public function findObjectFromSql($mainTable, $from, $filter = null, array $parameters = array(), $className = null)
    {
        $objects = $this->findObjectsFromSql($mainTable, $from, $filter, $parameters, null, self::MODE_ARRAY, $className);
        $page = $objects->take(0, 2);
        $count = $page->count();
        if ($count > 1) {
            throw new DuplicateRowException("Error while querying an object for table '$mainTable': More than 1 row have been returned, but we should have received at most one.");
        } elseif ($count === 0) {
            return;
        }

        return $page[0];
    }

    /**
     * @param string $mainTable
     * @param string $sql
     * @param array $parameters
     * @param $mode
     * @param string|null $className
     * @param string $sqlCount
     *
     * @return ResultIterator
     *
     * @throws TDBMException
     */
    public function findObjectsFromRawSql(string $mainTable, string $sql, array $parameters = array(), $mode, string $className = null, string $sqlCount = null)
    {
        // $mainTable is not secured in MagicJoin, let's add a bit of security to avoid SQL injection.
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $mainTable)) {
            throw new TDBMException(sprintf("Invalid table name: '%s'", $mainTable));
        }

        $mode = $mode ?: $this->mode;

        $queryFactory = new FindObjectsFromRawSqlQueryFactory($this, $this->tdbmSchemaAnalyzer->getSchema(), $mainTable, $sql, $sqlCount);

        return new ResultIterator($queryFactory, $parameters, $this->objectStorage, $className, $this, $this->magicQuery, $mode, $this->logger);
    }

    /**
     * Returns a unique bean according to the filters passed in parameter.
     * Throws a NoBeanFoundException if no bean was found for the filter passed in parameter.
     *
     * @param string            $mainTable             The name of the table queried
     * @param string|array|null $filter                The SQL filters to apply to the query (the WHERE part). All columns must be prefixed by the table name (in the form: table.column)
     * @param array             $parameters
     * @param array             $additionalTablesFetch
     * @param string            $className             Optional: The name of the class to instantiate. This class must extend the TDBMObject class. If none is specified, a TDBMObject instance will be returned
     *
     * @return AbstractTDBMObject The object we want
     *
     * @throws TDBMException
     */
    public function findObjectOrFail(string $mainTable, $filter = null, array $parameters = array(), array $additionalTablesFetch = array(), string $className = null)
    {
        $bean = $this->findObject($mainTable, $filter, $parameters, $additionalTablesFetch, $className);
        if ($bean === null) {
            throw new NoBeanFoundException("No result found for query on table '".$mainTable."'");
        }

        return $bean;
    }

    /**
     * @param array $beanData An array of data: array<table, array<column, value>>
     *
     * @return array an array with first item = class name, second item = table name and third item = list of tables needed
     *
     * @throws TDBMInheritanceException
     */
    public function _getClassNameFromBeanData(array $beanData)
    {
        if (count($beanData) === 1) {
            $tableName = array_keys($beanData)[0];
            $allTables = [$tableName];
        } else {
            $tables = [];
            foreach ($beanData as $table => $row) {
                $primaryKeyColumns = $this->getPrimaryKeyColumns($table);
                $pkSet = false;
                foreach ($primaryKeyColumns as $columnName) {
                    if ($row[$columnName] !== null) {
                        $pkSet = true;
                        break;
                    }
                }
                if ($pkSet) {
                    $tables[] = $table;
                }
            }

            // $tables contains the tables for this bean. Let's view the top most part of the hierarchy
            try {
                $allTables = $this->_getLinkBetweenInheritedTables($tables);
            } catch (TDBMInheritanceException $e) {
                throw TDBMInheritanceException::extendException($e, $this, $beanData);
            }
            $tableName = $allTables[0];
        }

        // Only one table in this bean. Life is sweat, let's look at its type:
        try {
            $className = $this->getBeanClassName($tableName);
        } catch (TDBMInvalidArgumentException $e) {
            $className = 'TheCodingMachine\\TDBM\\TDBMObject';
        }

        return [$className, $tableName, $allTables];
    }

    /**
     * Returns an item from cache or computes it using $closure and puts it in cache.
     *
     * @param string   $key
     * @param callable $closure
     *
     * @return mixed
     */
    private function fromCache(string $key, callable $closure)
    {
        $item = $this->cache->fetch($key);
        if ($item === false) {
            $item = $closure();
            $this->cache->save($key, $item);
        }

        return $item;
    }

    /**
     * Returns the foreign key object.
     *
     * @param string $table
     * @param string $fkName
     *
     * @return ForeignKeyConstraint
     */
    public function _getForeignKeyByName(string $table, string $fkName)
    {
        return $this->tdbmSchemaAnalyzer->getSchema()->getTable($table)->getForeignKey($fkName);
    }

    /**
     * @param $pivotTableName
     * @param AbstractTDBMObject $bean
     *
     * @return AbstractTDBMObject[]
     */
    public function _getRelatedBeans(string $pivotTableName, AbstractTDBMObject $bean)
    {
        list($localFk, $remoteFk) = $this->getPivotTableForeignKeys($pivotTableName, $bean);
        /* @var $localFk ForeignKeyConstraint */
        /* @var $remoteFk ForeignKeyConstraint */
        $remoteTable = $remoteFk->getForeignTableName();

        $primaryKeys = $this->getPrimaryKeyValues($bean);
        $columnNames = array_map(function ($name) use ($pivotTableName) {
            return $pivotTableName.'.'.$name;
        }, $localFk->getUnquotedLocalColumns());

        $filter = array_combine($columnNames, $primaryKeys);

        return $this->findObjects($remoteTable, $filter);
    }

    /**
     * @param $pivotTableName
     * @param AbstractTDBMObject $bean The LOCAL bean
     *
     * @return ForeignKeyConstraint[] First item: the LOCAL bean, second item: the REMOTE bean
     *
     * @throws TDBMException
     */
    private function getPivotTableForeignKeys(string $pivotTableName, AbstractTDBMObject $bean)
    {
        $fks = array_values($this->tdbmSchemaAnalyzer->getSchema()->getTable($pivotTableName)->getForeignKeys());
        $table1 = $fks[0]->getForeignTableName();
        $table2 = $fks[1]->getForeignTableName();

        $beanTables = array_map(function (DbRow $dbRow) {
            return $dbRow->_getDbTableName();
        }, $bean->_getDbRows());

        if (in_array($table1, $beanTables)) {
            return [$fks[0], $fks[1]];
        } elseif (in_array($table2, $beanTables)) {
            return [$fks[1], $fks[0]];
        } else {
            throw new TDBMException("Unexpected bean type in getPivotTableForeignKeys. Awaiting beans from table {$table1} and {$table2} for pivot table {$pivotTableName}");
        }
    }

    /**
     * Returns a list of pivot tables linked to $bean.
     *
     * @param AbstractTDBMObject $bean
     *
     * @return string[]
     */
    public function _getPivotTablesLinkedToBean(AbstractTDBMObject $bean)
    {
        $junctionTables = [];
        $allJunctionTables = $this->schemaAnalyzer->detectJunctionTables(true);
        foreach ($bean->_getDbRows() as $dbRow) {
            foreach ($allJunctionTables as $table) {
                // There are exactly 2 FKs since this is a pivot table.
                $fks = array_values($table->getForeignKeys());

                if ($fks[0]->getForeignTableName() === $dbRow->_getDbTableName() || $fks[1]->getForeignTableName() === $dbRow->_getDbTableName()) {
                    $junctionTables[] = $table->getName();
                }
            }
        }

        return $junctionTables;
    }

    /**
     * Array of types for tables.
     * Key: table name
     * Value: array of types indexed by column.
     *
     * @var array[]
     */
    private $typesForTable = [];

    /**
     * @internal
     *
     * @param string $tableName
     *
     * @return Type[]
     */
    public function _getColumnTypesForTable(string $tableName)
    {
        if (!isset($this->typesForTable[$tableName])) {
            $columns = $this->tdbmSchemaAnalyzer->getSchema()->getTable($tableName)->getColumns();
            foreach ($columns as $column) {
                $this->typesForTable[$tableName][$column->getName()] = $column->getType();
            }
        }

        return $this->typesForTable[$tableName];
    }

    /**
     * Sets the minimum log level.
     * $level must be one of Psr\Log\LogLevel::xxx.
     *
     * Defaults to LogLevel::WARNING
     *
     * @param string $level
     */
    public function setLogLevel(string $level)
    {
        $this->logger = new LevelFilter($this->rootLogger, $level);
    }
}
