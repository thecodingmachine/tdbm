<?php

namespace Mouf\Database\TDBM;

/*
 Copyright (C) 2006-2016 David Négrier - THE CODING MACHINE

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

use JsonSerializable;

/**
 * Instances of this class represent a "bean". Usually, a bean is mapped to a row of one table.
 * In some special cases (where inheritance is used), beans can be scattered on several tables.
 * Therefore, a TDBMObject is really a set of DbRow objects that represent one row in a table.
 *
 * @author David Negrier
 */
abstract class AbstractTDBMObject implements JsonSerializable
{
    /**
     * The service this object is bound to.
     *
     * @var TDBMService
     */
    protected $tdbmService;

    /**
     * An array of DbRow, indexed by table name.
     *
     * @var DbRow[]
     */
    protected $dbRows = array();

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
     * Array storing beans related via many to many relationships (pivot tables).
     *
     * @var \SplObjectStorage[] Key: pivot table name, value: SplObjectStorage
     */
    private $relationships = [];

    /**
     * @var bool[] Key: pivot table name, value: whether a query was performed to load the data.
     */
    private $loadedRelationships = [];

    /**
     * Used with $primaryKeys when we want to retrieve an existing object
     * and $primaryKeys=[] if we want a new object.
     *
     * @param string      $tableName
     * @param array       $primaryKeys
     * @param TDBMService $tdbmService
     *
     * @throws TDBMException
     * @throws TDBMInvalidOperationException
     */
    public function __construct($tableName = null, array $primaryKeys = array(), TDBMService $tdbmService = null)
    {
        // FIXME: lazy loading should be forbidden on tables with inheritance and dynamic type assignation...
        if (!empty($tableName)) {
            $this->dbRows[$tableName] = new DbRow($this, $tableName, $primaryKeys, $tdbmService);
        }

        if ($tdbmService === null) {
            $this->_setStatus(TDBMObjectStateEnum::STATE_DETACHED);
        } else {
            $this->_attach($tdbmService);
            if (!empty($primaryKeys)) {
                $this->_setStatus(TDBMObjectStateEnum::STATE_NOT_LOADED);
            } else {
                $this->_setStatus(TDBMObjectStateEnum::STATE_NEW);
            }
        }
    }

    /**
     * Alternative constructor called when data is fetched from database via a SELECT.
     *
     * @param array       $beanData    array<table, array<column, value>>
     * @param TDBMService $tdbmService
     */
    public function _constructFromData(array $beanData, TDBMService $tdbmService)
    {
        $this->tdbmService = $tdbmService;

        foreach ($beanData as $table => $columns) {
            $this->dbRows[$table] = new DbRow($this, $table, $tdbmService->_getPrimaryKeysFromObjectData($table, $columns), $tdbmService, $columns);
        }

        $this->status = TDBMObjectStateEnum::STATE_LOADED;
    }

    /**
     * Alternative constructor called when bean is lazily loaded.
     *
     * @param string      $tableName
     * @param array       $primaryKeys
     * @param TDBMService $tdbmService
     */
    public function _constructLazy($tableName, array $primaryKeys, TDBMService $tdbmService)
    {
        $this->tdbmService = $tdbmService;

        $this->dbRows[$tableName] = new DbRow($this, $tableName, $primaryKeys, $tdbmService);

        $this->status = TDBMObjectStateEnum::STATE_NOT_LOADED;
    }

    public function _attach(TDBMService $tdbmService)
    {
        if ($this->status !== TDBMObjectStateEnum::STATE_DETACHED) {
            throw new TDBMInvalidOperationException('Cannot attach an object that is already attached to TDBM.');
        }
        $this->tdbmService = $tdbmService;

        // If we attach this object, we must work to make sure the tables are in ascending order (from low level to top level)
        $tableNames = array_keys($this->dbRows);
        $tableNames = $this->tdbmService->_getLinkBetweenInheritedTables($tableNames);
        $tableNames = array_reverse($tableNames);

        $newDbRows = [];

        foreach ($tableNames as $table) {
            if (!isset($this->dbRows[$table])) {
                $this->registerTable($table);
            }
            $newDbRows[$table] = $this->dbRows[$table];
        }
        $this->dbRows = $newDbRows;

        $this->status = TDBMObjectStateEnum::STATE_NEW;
        foreach ($this->dbRows as $dbRow) {
            $dbRow->_attach($tdbmService);
        }
    }

    /**
     * Sets the state of the TDBM Object
     * One of TDBMObjectStateEnum::STATE_NEW, TDBMObjectStateEnum::STATE_NOT_LOADED, TDBMObjectStateEnum::STATE_LOADED, TDBMObjectStateEnum::STATE_DELETED.
     * $status = TDBMObjectStateEnum::STATE_NEW when a new object is created with DBMObject:getNewObject.
     * $status = TDBMObjectStateEnum::STATE_NOT_LOADED when the object has been retrieved with getObject but when no data has been accessed in it yet.
     * $status = TDBMObjectStateEnum::STATE_LOADED when the object is cached in memory.
     *
     * @param string $state
     */
    public function _setStatus($state)
    {
        $this->status = $state;

        // TODO: we might ignore the loaded => dirty state here! dirty status comes from the db_row itself.
        foreach ($this->dbRows as $dbRow) {
            $dbRow->_setStatus($state);
        }
    }

    protected function get($var, $tableName = null)
    {
        if ($tableName === null) {
            if (count($this->dbRows) > 1) {
                throw new TDBMException('This object is based on several tables. You must specify which table you are retrieving data from.');
            } elseif (count($this->dbRows) === 1) {
                $tableName = array_keys($this->dbRows)[0];
            }
        }

        if (!isset($this->dbRows[$tableName])) {
            if (count($this->dbRows[$tableName] === 0)) {
                throw new TDBMException('Object is not yet bound to any table.');
            } else {
                throw new TDBMException('Unknown table "'.$tableName.'"" in object.');
            }
        }

        return $this->dbRows[$tableName]->get($var);
    }

    protected function set($var, $value, $tableName = null)
    {
        if ($tableName === null) {
            if (count($this->dbRows) > 1) {
                throw new TDBMException('This object is based on several tables. You must specify which table you are retrieving data from.');
            } elseif (count($this->dbRows) === 1) {
                $tableName = array_keys($this->dbRows)[0];
            } else {
                throw new TDBMException('Please specify a table for this object.');
            }
        }

        if (!isset($this->dbRows[$tableName])) {
            $this->registerTable($tableName);
        }

        $this->dbRows[$tableName]->set($var, $value);
        if ($this->dbRows[$tableName]->_getStatus() === TDBMObjectStateEnum::STATE_DIRTY) {
            $this->status = TDBMObjectStateEnum::STATE_DIRTY;
        }
    }

    /**
     * @param string             $foreignKeyName
     * @param AbstractTDBMObject $bean
     */
    protected function setRef($foreignKeyName, AbstractTDBMObject $bean, $tableName = null)
    {
        if ($tableName === null) {
            if (count($this->dbRows) > 1) {
                throw new TDBMException('This object is based on several tables. You must specify which table you are retrieving data from.');
            } elseif (count($this->dbRows) === 1) {
                $tableName = array_keys($this->dbRows)[0];
            } else {
                throw new TDBMException('Please specify a table for this object.');
            }
        }

        if (!isset($this->dbRows[$tableName])) {
            $this->registerTable($tableName);
        }

        $this->dbRows[$tableName]->setRef($foreignKeyName, $bean);
        if ($this->dbRows[$tableName]->_getStatus() === TDBMObjectStateEnum::STATE_DIRTY) {
            $this->status = TDBMObjectStateEnum::STATE_DIRTY;
        }
    }

    /**
     * @param string $foreignKeyName A unique name for this reference
     *
     * @return AbstractTDBMObject|null
     */
    protected function getRef($foreignKeyName, $tableName = null)
    {
        if ($tableName === null) {
            if (count($this->dbRows) > 1) {
                throw new TDBMException('This object is based on several tables. You must specify which table you are retrieving data from.');
            } elseif (count($this->dbRows) === 1) {
                $tableName = array_keys($this->dbRows)[0];
            }
        }

        if (!isset($this->dbRows[$tableName])) {
            if (count($this->dbRows[$tableName] === 0)) {
                throw new TDBMException('Object is not yet bound to any table.');
            } else {
                throw new TDBMException('Unknown table "'.$tableName.'"" in object.');
            }
        }

        return $this->dbRows[$tableName]->getRef($foreignKeyName);
    }

    /**
     * Adds a many to many relationship to this bean.
     *
     * @param string             $pivotTableName
     * @param AbstractTDBMObject $remoteBean
     */
    protected function addRelationship($pivotTableName, AbstractTDBMObject $remoteBean)
    {
        $this->setRelationship($pivotTableName, $remoteBean, 'new');
    }

    /**
     * Returns true if there is a relationship to this bean.
     *
     * @param string             $pivotTableName
     * @param AbstractTDBMObject $remoteBean
     *
     * @return bool
     */
    protected function hasRelationship($pivotTableName, AbstractTDBMObject $remoteBean)
    {
        $storage = $this->retrieveRelationshipsStorage($pivotTableName);

        if ($storage->contains($remoteBean)) {
            if ($storage[$remoteBean]['status'] !== 'delete') {
                return true;
            }
        }

        return false;
    }

    /**
     * Internal TDBM method. Removes a many to many relationship from this bean.
     *
     * @param string             $pivotTableName
     * @param AbstractTDBMObject $remoteBean
     */
    public function _removeRelationship($pivotTableName, AbstractTDBMObject $remoteBean)
    {
        if (isset($this->relationships[$pivotTableName][$remoteBean]) && $this->relationships[$pivotTableName][$remoteBean]['status'] === 'new') {
            unset($this->relationships[$pivotTableName][$remoteBean]);
            unset($remoteBean->relationships[$pivotTableName][$this]);
        } else {
            $this->setRelationship($pivotTableName, $remoteBean, 'delete');
        }
    }

    /**
     * Returns the list of objects linked to this bean via $pivotTableName.
     *
     * @param $pivotTableName
     *
     * @return \SplObjectStorage
     */
    private function retrieveRelationshipsStorage($pivotTableName)
    {
        $storage = $this->getRelationshipStorage($pivotTableName);
        if ($this->status === TDBMObjectStateEnum::STATE_DETACHED || $this->status === TDBMObjectStateEnum::STATE_NEW || isset($this->loadedRelationships[$pivotTableName]) && $this->loadedRelationships[$pivotTableName]) {
            return $storage;
        }

        $beans = $this->tdbmService->_getRelatedBeans($pivotTableName, $this);
        $this->loadedRelationships[$pivotTableName] = true;

        foreach ($beans as $bean) {
            if (isset($storage[$bean])) {
                $oldStatus = $storage[$bean]['status'];
                if ($oldStatus === 'delete') {
                    // Keep deleted things deleted
                    continue;
                }
            }
            $this->setRelationship($pivotTableName, $bean, 'loaded');
        }

        return $storage;
    }

    /**
     * Internal TDBM method. Returns the list of objects linked to this bean via $pivotTableName.
     *
     * @param $pivotTableName
     *
     * @return AbstractTDBMObject[]
     */
    public function _getRelationships($pivotTableName)
    {
        return $this->relationshipStorageToArray($this->retrieveRelationshipsStorage($pivotTableName));
    }

    private function relationshipStorageToArray(\SplObjectStorage $storage)
    {
        $beans = [];
        foreach ($storage as $bean) {
            $statusArr = $storage[$bean];
            if ($statusArr['status'] !== 'delete') {
                $beans[] = $bean;
            }
        }

        return $beans;
    }

    /**
     * Declares a relationship between.
     *
     * @param string             $pivotTableName
     * @param AbstractTDBMObject $remoteBean
     * @param string             $status
     */
    private function setRelationship($pivotTableName, AbstractTDBMObject $remoteBean, $status)
    {
        $storage = $this->getRelationshipStorage($pivotTableName);
        $storage->attach($remoteBean, ['status' => $status, 'reverse' => false]);
        if ($this->status === TDBMObjectStateEnum::STATE_LOADED) {
            $this->_setStatus(TDBMObjectStateEnum::STATE_DIRTY);
        }

        $remoteStorage = $remoteBean->getRelationshipStorage($pivotTableName);
        $remoteStorage->attach($this, ['status' => $status, 'reverse' => true]);
    }

    /**
     * Returns the SplObjectStorage associated to this relationship (creates it if it does not exists).
     *
     * @param $pivotTableName
     *
     * @return \SplObjectStorage
     */
    private function getRelationshipStorage($pivotTableName)
    {
        if (isset($this->relationships[$pivotTableName])) {
            $storage = $this->relationships[$pivotTableName];
        } else {
            $storage = new \SplObjectStorage();
            $this->relationships[$pivotTableName] = $storage;
        }

        return $storage;
    }

    /**
     * Reverts any changes made to the object and resumes it to its DB state.
     * This can only be called on objects that come from database and that have not been deleted.
     * Otherwise, this will throw an exception.
     *
     * @throws TDBMException
     */
    public function discardChanges()
    {
        if ($this->status === TDBMObjectStateEnum::STATE_NEW || $this->status === TDBMObjectStateEnum::STATE_DETACHED) {
            throw new TDBMException("You cannot call discardChanges() on an object that has been created with the 'new' keyword and that has not yet been saved.");
        }

        if ($this->status === TDBMObjectStateEnum::STATE_DELETED) {
            throw new TDBMException('You cannot call discardChanges() on an object that has been deleted.');
        }

        $this->_setStatus(TDBMObjectStateEnum::STATE_NOT_LOADED);
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
    public function _getStatus()
    {
        return $this->status;
    }

    /**
     * Override the native php clone function for TDBMObjects.
     */
    public function __clone()
    {
        // Let's clone the many to many relationships
        if ($this->status === TDBMObjectStateEnum::STATE_DETACHED) {
            $pivotTableList = array_keys($this->relationships);
        } else {
            $pivotTableList = $this->tdbmService->_getPivotTablesLinkedToBean($this);
        }

        foreach ($pivotTableList as $pivotTable) {
            $storage = $this->retrieveRelationshipsStorage($pivotTable);

            // Let's duplicate the reverse side of the relationship
            foreach ($storage as $remoteBean) {
                $metadata = $storage[$remoteBean];

                $remoteStorage = $remoteBean->getRelationshipStorage($pivotTable);
                $remoteStorage->attach($this, ['status' => $metadata['status'], 'reverse' => !$metadata['reverse']]);
            }
        }

        // Let's clone each row
        foreach ($this->dbRows as $key => $dbRow) {
            $this->dbRows[$key] = clone $dbRow;
        }

        // Let's set the status to new (to enter the save function)
        $this->status = TDBMObjectStateEnum::STATE_DETACHED;
    }

    /**
     * Returns raw database rows.
     *
     * @return DbRow[] Key: table name, Value: DbRow object
     */
    public function _getDbRows()
    {
        return $this->dbRows;
    }

    private function registerTable($tableName)
    {
        $dbRow = new DbRow($this, $tableName);

        if (in_array($this->status, [TDBMObjectStateEnum::STATE_NOT_LOADED, TDBMObjectStateEnum::STATE_LOADED, TDBMObjectStateEnum::STATE_DIRTY])) {
            // Let's get the primary key for the new table
            $anotherDbRow = array_values($this->dbRows)[0];
            /* @var $anotherDbRow DbRow */
            $indexedPrimaryKeys = array_values($anotherDbRow->_getPrimaryKeys());
            $primaryKeys = $this->tdbmService->_getPrimaryKeysFromIndexedPrimaryKeys($tableName, $indexedPrimaryKeys);
            $dbRow->_setPrimaryKeys($primaryKeys);
        }

        $dbRow->_setStatus($this->status);

        $this->dbRows[$tableName] = $dbRow;
        // TODO: look at status (if not new)=> get primary key from tdbmservice
    }

    /**
     * Internal function: return the list of relationships.
     *
     * @return \SplObjectStorage[]
     */
    public function _getCachedRelationships()
    {
        return $this->relationships;
    }
}
