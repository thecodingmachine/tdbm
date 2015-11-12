<?php
namespace Mouf\Database\TDBM;

/*
 Copyright (C) 2006-2015 David Négrier - THE CODING MACHINE

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
use Doctrine\DBAL\Driver\Connection;
use Mouf\Database\TDBM\Filters\FilterInterface;


/**
 * Instances of this class represent a "bean". Usually, a bean is mapped to a row of one table.
 * In some special cases (where inheritance is used), beans can be scattered on several tables.
 * Therefore, a TDBMObject is really a set of DbRow objects that represent one row in a table.
 *
 * @author David Negrier
 */
abstract class AbstractTDBMObject implements \JsonSerializable, FilterInterface {

	/**
	 * The service this object is bound to.
	 * 
	 * @var TDBMService
	 */
	protected $tdbmService;

	/**
	 * An array of DbRow, indexed by table name.
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
	 * True if an error has occurred while saving. The user will have to call save() explicitly or to modify one of its members to save it again.
	 * TODO: hide this with getters and setters
	 *
	 * @var boolean
	 */
	public $db_onerror;

	private $db_connection;
	
	/**
	 * True to automatically save the object.
	 * If false, the user must explicitly call the save() method to save the object. 
	 * TODO: hide this with getters and setters
	 * 
	 * @var boolean
	 */
	public $db_autosave;


	/**
	 * Used with $primaryKeys when we want to retrieve an existing object
	 * and $primaryKeys=[] if we want a new object
	 *
	 * @param string $tableName
	 * @param array $primaryKeys
	 * @param TDBMService $tdbmService
	 * @throws TDBMException
	 * @throws TDBMInvalidOperationException
	 */
	public function __construct($tableName=null, array $primaryKeys=array(), TDBMService $tdbmService=null) {
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
	 * @param array $beanData array<table, array<column, value>>
	 * @param TDBMService $tdbmService
	 */
	public function _constructFromData(array $beanData, TDBMService $tdbmService) {
		$this->tdbmService = $tdbmService;

		foreach ($beanData as $table => $columns) {
			$this->dbRows[$table] = new DbRow($this, $table, $tdbmService->_getPrimaryKeysFromObjectData($table, $columns), $tdbmService, $columns);
		}

		$this->status = TDBMObjectStateEnum::STATE_LOADED;
	}

	/**
	 * Alternative constructor called when bean is lazily loaded.
	 *
	 * @param string $tableName
	 * @param array $primaryKeys
	 * @param TDBMService $tdbmService
	 */
	public function _constructLazy($tableName, array $primaryKeys, TDBMService $tdbmService) {
		$this->tdbmService = $tdbmService;

		$this->dbRows[$tableName] = new DbRow($this, $tableName, $primaryKeys, $tdbmService);

		$this->status = TDBMObjectStateEnum::STATE_NOT_LOADED;
	}

	public function _attach(TDBMService $tdbmService) {
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
	 * Returns true if the object will save automatically, false if an explicit call to save() is required.
	 *
	 * @return boolean
	 */
	public function getAutoSaveMode() {
		return $this->db_autosave;
	}
	
	/**
	 * Sets the autosave mode:
	 * true if the object will save automatically,
	 * false if an explicit call to save() is required.
	 *
	 * @param boolean $autoSave
	 */
	public function setAutoSaveMode($autoSave) {
		$this->db_autosave = $autoSave;
	}

	/**
	 * Sets the state of the TDBM Object
	 * One of TDBMObjectStateEnum::STATE_NEW, TDBMObjectStateEnum::STATE_NOT_LOADED, TDBMObjectStateEnum::STATE_LOADED, TDBMObjectStateEnum::STATE_DELETED.
	 * $status = TDBMObjectStateEnum::STATE_NEW when a new object is created with DBMObject:getNewObject.
	 * $status = TDBMObjectStateEnum::STATE_NOT_LOADED when the object has been retrieved with getObject but when no data has been accessed in it yet.
	 * $status = TDBMObjectStateEnum::STATE_LOADED when the object is cached in memory.
	 * @param string $state
	 */
	public function _setStatus($state){
		$this->status = $state;

		foreach ($this->dbRows as $dbRow) {
			$dbRow->_setStatus($state);
		}
	}

	public function get($var, $tableName = null) {
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

	/**
	 * Returns true if a column is set, false otherwise.
	 * 
	 * @param string $var
	 * @return boolean
	 */
	public function has($var, $tableName = null) {
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

		return $this->dbRows[$tableName]->has($var);
	}
	
	public function set($var, $value, $tableName = null) {
		if ($tableName === null) {
			if (count($this->dbRows) > 1) {
				throw new TDBMException('This object is based on several tables. You must specify which table you are retrieving data from.');
			} elseif (count($this->dbRows) === 1) {
				$tableName = array_keys($this->dbRows)[0];
			} else {
				throw new TDBMException("Please specify a table for this object.");
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
	 * @param string $foreignKeyName
	 * @param AbstractTDBMObject $bean
	 */
	public function setRef($foreignKeyName, AbstractTDBMObject $bean, $tableName = null) {
		if ($tableName === null) {
			if (count($this->dbRows) > 1) {
				throw new TDBMException('This object is based on several tables. You must specify which table you are retrieving data from.');
			} elseif (count($this->dbRows) === 1) {
				$tableName = array_keys($this->dbRows)[0];
			} else {
				throw new TDBMException("Please specify a table for this object.");
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
	 * @return AbstractTDBMObject|null
	 */
	public function getRef($foreignKeyName, $tableName = null) {
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

	/*public function __destruct() {
		// In a destructor, no exception can be thrown (PHP 5 limitation)
		// So we print the error instead
		try {
			if (!$this->db_onerror && $this->db_autosave)
			{
				$this->save();
			}
		} catch (\Exception $e) {
			trigger_error($e->getMessage(), E_USER_ERROR);
		}
	}*/


	/**
	 * Reverts any changes made to the object and resumes it to its DB state.
	 * This can only be called on objects that come from database and that have not been deleted.
	 * Otherwise, this will throw an exception.
	 *
	 */
	public function discardChanges() {
		if ($this->status == TDBMObjectStateEnum::STATE_NEW) {
			throw new TDBMException("You cannot call discardChanges() on an object that has been created with getNewObject and that has not yet been saved.");
		}

		if ($this->status == TDBMObjectStateEnum::STATE_DELETED) {
			throw new TDBMException("You cannot call discardChanges() on an object that has been deleted.");
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
	public function _getStatus() {
		return $this->status;
	}

	
	/**
	 * Implement the unique JsonSerializable method
	 * @return array
	 */
	public function jsonSerialize(){
		// FIXME
		$this->_dbLoadIfNotLoaded();
		return $this->dbRow;
	}

	/**
	 * Returns the SQL of the filter (the SQL WHERE clause).
	 *
	 * @param Connection $dbConnection
	 * @return string
	 */
	public function toSql(Connection $dbConnection) {
		return $this->getPrimaryKeyWhereStatement();
	}

	/**
	 * Returns the tables used in the filter in an array.
	 *
	 * @return array<string>
	 */
	public function getUsedTables() {
		return array_keys($this->dbRows);
	}

	/**
	 * Returns Where statement to query this object
	 *
	 * @return string
	 */
	private function getPrimaryKeyWhereStatement () {
		// Let's first get the primary keys
		$pk_table = $this->tdbmService->getPrimaryKeyColumns($this->dbTableName);
		// Now for the object_id
		$object_id = $this->TDBMObject_id;
		// If there is only one primary key:
		if (count($pk_table)==1) {
			$sql_where = $this->db_connection->escapeDBItem($this->dbTableName).'.'.$this->db_connection->escapeDBItem($pk_table[0])."=".$this->db_connection->quoteSmart($this->TDBMObject_id);
		} else {
			$ids = unserialize($object_id);
			$i=0;
			$sql_where_array = array();
			foreach ($pk_table as $pk) {
				$sql_where_array[] = $this->db_connection->escapeDBItem($this->dbTableName).'.'.$this->db_connection->escapeDBItem($pk)."=".$this->db_connection->quoteSmart($ids[$i]);
				$i++;
			}
			$sql_where = implode(" AND ",$sql_where_array);
		}
		return $sql_where;
	}

    /**
     * Override the native php clone function for TDBMObjects
     */
    public function __clone(){
        $this->_dbLoadIfNotLoaded();
        //First lets set the status to new (to enter the save function)
        $this->status = TDBMObjectStateEnum::STATE_NEW;

        // Add the current TDBMObject to the save object list
        $this->tdbmService->_addToToSaveObjectList($this);

        //Now unset the PK from the row
        $pk_array = $this->tdbmService->getPrimaryKeyColumns($this->dbTableName);
        foreach ($pk_array as $pk) {
            $this->dbRow[$pk] = null;
        }
    }

	/**
	 * Returns raw database rows.
	 *
	 * @return DbRow[] Key: table name, Value: DbRow object
	 */
	public function _getDbRows() {
		return $this->dbRows;
	}

	private function registerTable($tableName) {
		$dbRow = new DbRow($this, $tableName);

		if (in_array($this->status, [ TDBMObjectStateEnum::STATE_NOT_LOADED, TDBMObjectStateEnum::STATE_LOADED, TDBMObjectStateEnum::STATE_DIRTY ])) {
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

}
