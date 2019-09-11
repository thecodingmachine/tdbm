<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM;

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

use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\Query\ManyToOnePartialQuery;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\Query\PartialQuery;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\StorageNode;
use TheCodingMachine\TDBM\Schema\ForeignKeys;
use function array_pop;
use function count;
use function var_export;

/**
 * Instances of this class represent a row in a database.
 *
 * @author David Negrier
 */
class DbRow
{
    /**
     * The service this object is bound to.
     *
     * @var TDBMService|null
     */
    protected $tdbmService;

    /**
     * The object containing this db row.
     *
     * @var AbstractTDBMObject
     */
    private $object;

    /**
     * The name of the table the object if issued from.
     *
     * @var string
     */
    private $dbTableName;

    /**
     * The array of columns returned from database.
     *
     * @var mixed[]
     */
    private $dbRow = [];

    /**
     * The array of beans this bean points to, indexed by foreign key name.
     *
     * @var AbstractTDBMObject[]
     */
    private $references = [];

    /**
     * One of TDBMObjectStateEnum::STATE_NEW, TDBMObjectStateEnum::STATE_NOT_LOADED, TDBMObjectStateEnum::STATE_LOADED, TDBMObjectStateEnum::STATE_DELETED.
     * $status = TDBMObjectStateEnum::STATE_NEW when a new object is created with DBMObject:getNewObject.
     * $status = TDBMObjectStateEnum::STATE_NOT_LOADED when the object has been retrieved with getObject but when no data has been accessed in it yet.
     * $status = TDBMObjectStateEnum::STATE_LOADED when the object is cached in memory.
     *
     * @var string
     */
    private $status;

    /**
     * The values of the primary key.
     * This is set when the object is in "loaded" or "not loaded" state.
     *
     * @var array An array of column => value
     */
    private $primaryKeys;

    /**
     * A list of modified columns, indexed by column name. Value is always true.
     *
     * @var array
     */
    private $modifiedColumns = [];

    /**
     * A list of modified references, indexed by foreign key name. Value is always true.
     *
     * @var array
     */
    private $modifiedReferences = [];
    /**
     * @var ForeignKeys
     */
    private $foreignKeys;
    /**
     * @var PartialQuery|null
     */
    private $partialQuery;

    /**
     * You should never call the constructor directly. Instead, you should use the
     * TDBMService class that will create TDBMObjects for you.
     *
     * Used with id!=false when we want to retrieve an existing object
     * and id==false if we want a new object
     *
     * @param AbstractTDBMObject $object The object containing this db row
     * @param string $tableName
     * @param mixed[] $primaryKeys
     * @param TDBMService $tdbmService
     * @param mixed[] $dbRow
     * @throws TDBMException
     */
    public function __construct(AbstractTDBMObject $object, string $tableName, ForeignKeys $foreignKeys, array $primaryKeys = array(), TDBMService $tdbmService = null, array $dbRow = [], ?PartialQuery $partialQuery = null)
    {
        $this->object = $object;
        $this->dbTableName = $tableName;
        $this->foreignKeys = $foreignKeys;
        $this->partialQuery = $partialQuery;

        $this->status = TDBMObjectStateEnum::STATE_DETACHED;

        if ($tdbmService === null) {
            if (!empty($primaryKeys)) {
                throw new TDBMException('You cannot pass an id to the DbRow constructor without passing also a TDBMService.');
            }
        } else {
            $this->tdbmService = $tdbmService;

            if (!empty($primaryKeys)) {
                $this->_setPrimaryKeys($primaryKeys);
                if (!empty($dbRow)) {
                    $this->dbRow = $dbRow;
                    $this->status = TDBMObjectStateEnum::STATE_LOADED;
                } else {
                    $this->status = TDBMObjectStateEnum::STATE_NOT_LOADED;
                }
                $tdbmService->_addToCache($this);
            } else {
                $this->status = TDBMObjectStateEnum::STATE_NEW;
                $this->tdbmService->_addToToSaveObjectList($this);
            }
        }
    }

    public function _attach(TDBMService $tdbmService): void
    {
        if ($this->status !== TDBMObjectStateEnum::STATE_DETACHED) {
            throw new TDBMInvalidOperationException('Cannot attach an object that is already attached to TDBM.');
        }
        $this->tdbmService = $tdbmService;
        $this->status = TDBMObjectStateEnum::STATE_NEW;
        $this->tdbmService->_addToToSaveObjectList($this);
    }

    /**
     * Sets the state of the TDBM Object
     * One of TDBMObjectStateEnum::STATE_NEW, TDBMObjectStateEnum::STATE_NOT_LOADED, TDBMObjectStateEnum::STATE_LOADED, TDBMObjectStateEnum::STATE_DELETED.
     * $status = TDBMObjectStateEnum::STATE_NEW when a new object is created with the "new" keyword.
     * $status = TDBMObjectStateEnum::STATE_NOT_LOADED when the object has been retrieved with getObject but when no data has been accessed in it yet.
     * $status = TDBMObjectStateEnum::STATE_LOADED when the object is cached in memory.
     *
     * @param string $state
     */
    public function _setStatus(string $state) : void
    {
        $this->status = $state;
        if ($state === TDBMObjectStateEnum::STATE_LOADED) {
            // after saving we are back to a loaded state, hence unmodified.
            $this->modifiedColumns = [];
            $this->modifiedReferences = [];
        }
    }

    /**
     * When discarding a bean, we expect to reload data from the DB, not the cache.
     * Hence, we must disable smart eager load.
     */
    public function disableSmartEagerLoad(): void
    {
        $this->partialQuery = null;
    }

    /**
     * This is an internal method. You should not call this method yourself. The TDBM library will do it for you.
     * If the object is in state 'not loaded', this method performs a query in database to load the object.
     *
     * A TDBMException is thrown is no object can be retrieved (for instance, if the primary key specified
     * cannot be found).
     */
    public function _dbLoadIfNotLoaded(): void
    {
        if ($this->status === TDBMObjectStateEnum::STATE_NOT_LOADED) {
            if ($this->tdbmService === null) {
                throw new TDBMException('DbRow initialization failed. tdbmService is null but status is STATE_NOT_LOADED'); // @codeCoverageIgnore
            }
            $connection = $this->tdbmService->getConnection();

            if ($this->partialQuery !== null) {
                $this->partialQuery->registerDataLoader($connection);

                // Let's get the data loader.
                $dataLoader = $this->partialQuery->getStorageNode()->getManyToOneDataLoader($this->partialQuery->getKey());

                if (count($this->primaryKeys) !== 1) {
                    throw new \RuntimeException('Data-loader patterns only supports primary keys on one column. Table "'.$this->dbTableName.'" has a PK on '.count($this->primaryKeys). ' columns'); // @codeCoverageIgnore
                }
                $pks = $this->primaryKeys;
                $pkId = array_pop($pks);

                $row = $dataLoader->get((string) $pkId);
            } else {
                list($sql_where, $parameters) = $this->tdbmService->buildFilterFromFilterBag($this->primaryKeys, $connection->getDatabasePlatform());

                $sql = 'SELECT * FROM '.$connection->quoteIdentifier($this->dbTableName).' WHERE '.$sql_where;
                $result = $connection->executeQuery($sql, $parameters);

                $row = $result->fetch(\PDO::FETCH_ASSOC);

                $result->closeCursor();

                if ($row === false) {
                    throw new NoBeanFoundException("Could not retrieve object from table \"$this->dbTableName\" using filter \"$sql_where\" with data \"".var_export($parameters, true). '".');
                }
            }



            $this->dbRow = [];
            $types = $this->tdbmService->_getColumnTypesForTable($this->dbTableName);

            foreach ($row as $key => $value) {
                $this->dbRow[$key] = $types[$key]->convertToPHPValue($value, $connection->getDatabasePlatform());
            }

            $this->status = TDBMObjectStateEnum::STATE_LOADED;
        }
    }

    /**
     * @return mixed|null
     */
    public function get(string $var)
    {
        if (!isset($this->primaryKeys[$var])) {
            $this->_dbLoadIfNotLoaded();
        }

        return $this->dbRow[$var] ?? null;
    }

    /**
     * @param string $var
     * @param mixed $value
     * @throws TDBMException
     */
    public function set(string $var, $value): void
    {
        $this->_dbLoadIfNotLoaded();

        /*
        // Ok, let's start by checking the column type
        $type = $this->db_connection->getColumnType($this->dbTableName, $var);

        // Throws an exception if the type is not ok.
        if (!$this->db_connection->checkType($value, $type)) {
            throw new TDBMException("Error! Invalid value passed for attribute '$var' of table '$this->dbTableName'. Passed '$value', but expecting '$type'");
        }
        */

        /*if ($var == $this->getPrimaryKey() && isset($this->dbRow[$var]))
            throw new TDBMException("Error! Changing primary key value is forbidden.");*/
        $this->dbRow[$var] = $value;
        $this->modifiedColumns[$var] = true;
        if ($this->tdbmService !== null && $this->status === TDBMObjectStateEnum::STATE_LOADED) {
            $this->status = TDBMObjectStateEnum::STATE_DIRTY;
            $this->tdbmService->_addToToSaveObjectList($this);
        }
    }

    /**
     * @param string             $foreignKeyName
     * @param AbstractTDBMObject $bean
     */
    public function setRef(string $foreignKeyName, AbstractTDBMObject $bean = null): void
    {
        $this->references[$foreignKeyName] = $bean;
        $this->modifiedReferences[$foreignKeyName] = true;

        if ($this->tdbmService !== null && $this->status === TDBMObjectStateEnum::STATE_LOADED) {
            $this->status = TDBMObjectStateEnum::STATE_DIRTY;
            $this->tdbmService->_addToToSaveObjectList($this);
        }
    }

    /**
     * @param string $foreignKeyName A unique name for this reference
     *
     * @return AbstractTDBMObject|null
     */
    public function getRef(string $foreignKeyName) : ?AbstractTDBMObject
    {
        if (array_key_exists($foreignKeyName, $this->references)) {
            return $this->references[$foreignKeyName];
        } elseif ($this->status === TDBMObjectStateEnum::STATE_NEW || $this->tdbmService === null) {
            // If the object is new and has no property, then it has to be empty.
            return null;
        } else {
            $this->_dbLoadIfNotLoaded();

            // Let's match the name of the columns to the primary key values
            $fk = $this->foreignKeys->getForeignKey($foreignKeyName);

            $values = [];
            $localColumns = $fk->getUnquotedLocalColumns();
            foreach ($localColumns as $column) {
                if (!isset($this->dbRow[$column])) {
                    return null;
                }
                $values[] = $this->dbRow[$column];
            }

            $foreignColumns = $fk->getUnquotedForeignColumns();
            $foreignTableName = $fk->getForeignTableName();

            $filter = SafeFunctions::arrayCombine($foreignColumns, $values);

            // If the foreign key points to the primary key, let's use findObjectByPk
            if ($this->tdbmService->getPrimaryKeyColumns($foreignTableName) === $foreignColumns) {
                if ($this->partialQuery !== null && count($foreignColumns) === 1) {
                    // Optimisation: let's build the smart eager load query we need to fetch more than one object at once.
                    $newPartialQuery = new ManyToOnePartialQuery($this->partialQuery, $this->dbTableName, $fk->getForeignTableName(), $foreignColumns[0], $localColumns[0]);
                } else {
                    $newPartialQuery = null;
                }
                $ref = $this->tdbmService->findObjectByPk($foreignTableName, $filter, [], true, null, $newPartialQuery);
            } else {
                $ref = $this->tdbmService->findObject($foreignTableName, $filter);
            }
            $this->references[$foreignKeyName] = $ref;
            return $ref;
        }
    }

    /**
     * Returns the name of the table this object comes from.
     *
     * @return string
     */
    public function _getDbTableName(): string
    {
        return $this->dbTableName;
    }

    /**
     * Method used internally by TDBM. You should not use it directly.
     * This method returns the status of the TDBMObject.
     * This is one of TDBMObjectStateEnum::STATE_NEW, TDBMObjectStateEnum::STATE_NOT_LOADED, TDBMObjectStateEnum::STATE_LOADED, TDBMObjectStateEnum::STATE_DELETED.
     * $status = TDBMObjectStateEnum::STATE_NEW when a new object is created with DBMObject:getNewObject.
     * $status = TDBMObjectStateEnum::STATE_NOT_LOADED when the object has been retrieved with getObject but when no data has been accessed in it yet.
     * $status = TDBMObjectStateEnum::STATE_LOADED when the object is cached in memory.
     *
     * @return string
     */
    public function _getStatus(): string
    {
        return $this->status;
    }

    /**
     * Override the native php clone function for TDBMObjects.
     */
    public function __clone()
    {
        // Let's load the row (before we lose the ID!)
        $this->_dbLoadIfNotLoaded();

        //Let's set the status to detached
        $this->status = TDBMObjectStateEnum::STATE_DETACHED;

        $this->primaryKeys = [];

        //Now unset the PK from the row
        if ($this->tdbmService) {
            $pk_array = $this->tdbmService->getPrimaryKeyColumns($this->dbTableName);
            foreach ($pk_array as $pk) {
                unset($this->dbRow[$pk]);
            }
        }
    }

    /**
     * Returns raw database row.
     *
     * @return mixed[]
     *
     * @throws TDBMMissingReferenceException
     */
    public function _getDbRow(): array
    {
        return $this->buildDbRow($this->dbRow, $this->references);
    }

    /**
     * Returns raw database row that needs to be updated.
     *
     * @return mixed[]
     *
     * @throws TDBMMissingReferenceException
     */
    public function _getUpdatedDbRow(): array
    {
        $dbRow = \array_intersect_key($this->dbRow, $this->modifiedColumns);
        $references = \array_intersect_key($this->references, $this->modifiedReferences);
        return $this->buildDbRow($dbRow, $references);
    }

    /**
     * Builds a raw db row from dbRow and references passed in parameters.
     *
     * @param mixed[] $dbRow
     * @param array<string,AbstractTDBMObject|null> $references
     * @return mixed[]
     * @throws TDBMMissingReferenceException
     */
    private function buildDbRow(array $dbRow, array $references): array
    {
        if ($this->tdbmService === null) {
            throw new TDBMException('DbRow initialization failed. tdbmService is null.'); // @codeCoverageIgnore
        }

        // Let's merge $dbRow and $references
        foreach ($references as $foreignKeyName => $reference) {
            // Let's match the name of the columns to the primary key values
            $fk = $this->foreignKeys->getForeignKey($foreignKeyName);
            $localColumns = $fk->getUnquotedLocalColumns();

            if ($reference !== null) {
                $refDbRows = $reference->_getDbRows();
                $firstRefDbRow = reset($refDbRows);
                if ($firstRefDbRow === false) {
                    throw new \RuntimeException('Unexpected error: empty refDbRows'); // @codeCoverageIgnore
                }
                if ($firstRefDbRow->_getStatus() === TDBMObjectStateEnum::STATE_DELETED) {
                    throw TDBMMissingReferenceException::referenceDeleted($this->dbTableName, $reference);
                }
                $foreignColumns = $fk->getUnquotedForeignColumns();
                $refBeanValues = $firstRefDbRow->dbRow;
                for ($i = 0, $count = \count($localColumns); $i < $count; ++$i) {
                    $dbRow[$localColumns[$i]] = $refBeanValues[$foreignColumns[$i]];
                }
            } else {
                for ($i = 0, $count = \count($localColumns); $i < $count; ++$i) {
                    $dbRow[$localColumns[$i]] = null;
                }
            }
        }

        return $dbRow;
    }

    /**
     * Returns references array.
     *
     * @return AbstractTDBMObject[]
     */
    public function _getReferences(): array
    {
        return $this->references;
    }

    /**
     * Returns the values of the primary key.
     * This is set when the object is in "loaded" state.
     *
     * @return mixed[]
     */
    public function _getPrimaryKeys(): array
    {
        return $this->primaryKeys;
    }

    /**
     * Sets the values of the primary key.
     * This is set when the object is in "loaded" or "not loaded" state.
     *
     * @param mixed[] $primaryKeys
     */
    public function _setPrimaryKeys(array $primaryKeys): void
    {
        $this->primaryKeys = $primaryKeys;
        foreach ($this->primaryKeys as $column => $value) {
            // Warning: in case of multi-columns with one being a reference, the $dbRow will contain a reference column (which is not the case elsewhere in the application)
            $this->dbRow[$column] = $value;
        }
    }

    /**
     * Returns the TDBMObject this bean is associated to.
     *
     * @return AbstractTDBMObject
     */
    public function getTDBMObject(): AbstractTDBMObject
    {
        return $this->object;
    }

    /**
     * Sets the TDBMObject this bean is associated to.
     * Only used when cloning.
     *
     * @param AbstractTDBMObject $object
     */
    public function setTDBMObject(AbstractTDBMObject $object): void
    {
        $this->object = $object;
    }
}
