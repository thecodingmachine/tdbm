<?php
namespace Mouf\Database\TDBM;
/*
 Copyright (C) 2006-2009 David Négrier - THE CODING MACHINE

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


/**
 * Instances of this class represent an object that is bound to a row in a database table.
 * You access access the rows using there name, as a property of an object, or as a table.
 * For instance:
 * 	<code>$tdbmObject->myrow</code>
 * or
 * 	<code>$tdbmObject['myrow']</code>
 * are both valid.
 * 
 * @author David Negrier
 */
class TDBMObject implements \ArrayAccess, \Iterator {

	/**
	 * The service this object is bound to.
	 * 
	 * @var TDBMService
	 */
	protected $tdbmService;
	
	/**
	 * The name of the table the object if issued from
	 *
	 * @var string
	 */
	private $db_table_name;

	/**
	 * The array of columns returned from database.
	 *
	 * TODO: hide this with getters and setters
	 * @var array
	 */
	public $db_row;

	/**
	 * One of "new", "not loaded", "loaded", "deleted".
	 * $TDBMObject_state = "new" when a new object is created with DBMObject:getNewObject.
	 * $TDBMObject_state = "not loaded" when the object has been retrieved with getObject but when no data has been accessed in it yet.
	 * $TDBMObject_state = "loaded" when the object is cached in memory.
	 *
	 * @var string
	 */
	private $TDBMObject_state;

	/**
	 * True if the object has been modified and must be saved.
	 *
	 * @var boolean
	 */
	private $db_modified_state;

	/**
	 * True if an error has occured while saving. The user will have to call save() explicitly or to modify one of its members to save it again.
	 * TODO: hide this with getters and setters
	 *
	 * @var boolean
	 */
	public $db_onerror;

	// TODO: make this private again.
	public $TDBMObject_id;

	/**
	 * dependency between columns of objects and linked objects in the form: $this->dependency[$row] = $object
	 *
	 * Used in setonestar... TODO
	 */
	private $db_dependency;

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
	 * You should never call the constructor directly. Instead, you should use the 
	 * TDBMService class that will create TDBMObjects for you.
	 * 
	 * Used with id!=false when we want to retrieve an existing object
	 * and id==false if we want a new object
	 *
	 * @param TDBMService $tdbmService
	 * @param string $table_name
	 * @param mixed $id
	 */
	public function __construct(TDBMService $tdbmService, $table_name, $id=false) {
		$this->tdbmService = $tdbmService;
		$this->db_connection = $this->tdbmService->getConnection();
		$this->db_table_name = $table_name;
		$this->TDBMObject_id = $id;
		$this->db_onerror = false;
		if ($id !== false) {
			$this->TDBMObject_state = "not loaded";
			$this->db_modified_state = false;		
		} else {
			$this->TDBMObject_state = "new";
			$this->db_modified_state = true;
		}
		
		$this->db_autosave = $this->tdbmService->getDefaultAutoSaveMode();
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
	 * @param unknown_type $autoSave
	 * @return boolean
	 */
	public function setAutoSaveMode($autoSave) {
		$this->db_autosave = $autoSave;
	}
	
	/**
	 * Returns the state of the TDBM Object
	 * One of "new", "not loaded", "loaded", "deleted".
	 * $TDBMObject_state = "new" when a new object is created with DBMObject:getNewObject.
	 * $TDBMObject_state = "not loaded" when the object has been retrieved with getObject but when no data has been accessed in it yet.
	 * $TDBMObject_state = "loaded" when the object is cached in memory.
	 * @return string
	 */
	public function getTDBMObjectState(){
		return $this->TDBMObject_state;
	}
	
	/**
	 * Sets the state of the TDBM Object
	 * One of "new", "not loaded", "loaded", "deleted".
	 * $TDBMObject_state = "new" when a new object is created with DBMObject:getNewObject.
	 * $TDBMObject_state = "not loaded" when the object has been retrieved with getObject but when no data has been accessed in it yet.
	 * $TDBMObject_state = "loaded" when the object is cached in memory.
	 * @param string $state
	 */
	public function setTDBMObjectState($state){
		$this->TDBMObject_state = $state;	
	}
	
	/**
	 * Internal TDBM method, you should not use this.
	 * Loads the db_row property of the object from the $row array.
	 * Any row having a key starting with 'tdbm_reserved_col_' is ignored.
	 *
	 * @param array $row
	 */
	public function loadFromRow($row) {
		foreach ($row as $key=>$value) {
			if (strpos($key, 'tdbm_reserved_col_')!==0) {
				$this->db_row[$key]=$value;
			}
		}

		$this->TDBMObject_state = "loaded";
	}

	/**
	 * Returns an array of the columns composing the primary key for that object.
	 * This methods caches the primary keys so that if it is called twice, the second call will
	 * not make any query to the database.
	 *
	 * TODO: move this into TDBMService
	 * @return array
	 */
	public function getPrimaryKey() {
		return $this->tdbmService->getPrimaryKeyStatic($this->db_table_name);
	}

	/**
	 * This is an internal method. You should not call this method yourself. The TDBM library will do it for you.
	 * If the object is in state 'not loaded', this method performs a query in database to load the object.
	 *
	 * A TDBMException is thrown is no object can be retrieved (for instance, if the primary key specified
	 * cannot be found).
	 */
	public function _dbLoadIfNotLoaded() {
		if ($this->TDBMObject_state == "not loaded")
		{
			// Let's first get the primary keys
			$pk_table = $this->getPrimaryKey();
			// Now for the object_id
			$object_id = $this->TDBMObject_id;
			// If there is only one primary key:
			if (count($pk_table)==1) {
				$sql_where = $this->db_connection->escapeDBItem($pk_table[0])."=".$this->db_connection->quoteSmart($this->TDBMObject_id);
			} else {
				$ids = unserialize($object_id);
				$i=0;
				$sql_where_array = array();
				foreach ($pk_table as $pk) {
					$sql_where_array[] = $this->db_connection->escapeDBItem($pk)."=".$this->db_connection->quoteSmart($ids[$i]);
					$i++;
				}
				$sql_where = implode(" AND ",$sql_where_array);
			}

			$sql = "SELECT * FROM ".$this->db_connection->escapeDBItem($this->db_table_name)." WHERE ".$sql_where;
			$result = $this->db_connection->query($sql);


			if ($result->rowCount()==0)
			{
				throw new TDBMException("Could not retrieve object from table \"$this->db_table_name\" with ID \"".$this->TDBMObject_id."\".");
			}

			$fullCaseRow = $result->fetchAll(\PDO::FETCH_ASSOC);
			
			$result->closeCursor();
			$result = null;
				
			$this->db_row = array();
			foreach ($fullCaseRow[0] as $key=>$value)  {
				$this->db_row[$this->db_connection->toStandardCaseColumn($key)]=$value;
			}
			
			$this->TDBMObject_state = "loaded";
		}
	}

	public function __get($var) {
		$this->_dbLoadIfNotLoaded();

		// Let's first check if the key exist.
		if (!array_key_exists($var, $this->db_row)) {
		
			// The key does not exist? Does a lower case key exist?
			// Note: we only call it after the first array_key_exists because
			// a call to toStandardcaseColumn is quite slow in PHP.
			$var = $this->db_connection->toStandardcaseColumn($var);
	
			if (!array_key_exists($var, $this->db_row)) {
				// Unable to find column.... this is an error if the object has been retrieved from database.
				// If it's a new object, well, that may not be an error after all!
				// Let's check if the column does exist in the table
				$column_exist = $this->db_connection->checkColumnExist($this->db_table_name, $var);
				// If the column DOES exist, then the object is new, and therefore, we should
				// return null.
				if ($column_exist === true) {
					return null;
				}
	
				// Let's try to be accurate in error reporting. The checkColumnExist returns an array of closest matches.
				$result_array = $column_exist;
				
				if (count($result_array)==1)
				$str = "Could not find column \"$var\" in table \"$this->db_table_name\". Maybe you meant this column: '".$result_array[0]."'";
				else
				$str = "Could not find column \"$var\" in table \"$this->db_table_name\". Maybe you meant one of those columns: '".implode("', '",$result_array)."'";
	
	
				throw new TDBMException($str);
			}
		}
		return $this->db_row[$var];
	}

	/**
	 * Returns true if a column is set, false otherwise.
	 * 
	 * @param string $var
	 * @return boolean
	 */
	public function __isset($var) {
		$this->_dbLoadIfNotLoaded();

		// Let's only deal with lower case.
		$var = $this->db_connection->toStandardcaseColumn($var);
		
		return isset($this->db_row[$var]);
	}
	
	public function __set($var, $value) {
		$this->_dbLoadIfNotLoaded();

		// Let's only deal with lower case.
		$var = $this->db_connection->toStandardcaseColumn($var);

		// Ok, let's start by checking the column type
		$type = $this->db_connection->getColumnType($this->db_table_name, $var);

		// Throws an exception if the type is not ok.
		if (!$this->db_connection->checkType($value, $type)) {
			throw new TDBMException("Error! Invalid value passed for attribute '$var' of table '$this->db_table_name'. Passed '$value', but expecting '$type'");
		}
		
		// TODO: we should be able to set the primary key if the object is new....

		if (isset($this->db_row[$var])) {
			foreach ($this->getPrimaryKey() as $pk) {
				if ($pk == $var) {
					throw new TDBMException("Error! Changing primary key value is forbidden.");
				}
			}
		}

		/*if ($var == $this->getPrimaryKey() && isset($this->db_row[$var]))
			throw new TDBMException("Error! Changing primary key value is forbidden.");*/
		$this->db_row[$var] = $value;
		if ($this->db_modified_state == false) {
			$this->db_modified_state = true;
			$this->tdbmService->_addToToSaveObjectList($this);
		}
		// Unset the error since something has changed (Insert or Update could work this time).
		$this->db_onerror = false;
	}

	/**
	 * Saves the current object by INSERTing or UPDAT(E)ing it in the database.
	 */
	public function save() {
		if (!is_array($this->db_row)) {
			return;
		}

		if ($this->TDBMObject_state == "new") {

			// Let's see if the columns for primary key have been set before inserting.
			// We assume that if one of the value of the PK has been set, the values have not been changed.
			$pk_set = false;
			$pk_array = $this->getPrimaryKey();
			foreach ($pk_array as $pk) {
				if ($this->db_row[$pk]!==null) {
					$pk_set=true;
				}
			}
			// if there are many columns for the PK, and none is set, we have no way to find the object back!
			// let's go on error
			if (count($pk_array)>1 && !$pk_set) {
				$msg = "Error! You did not set the primary keys for the new object of type '$this->db_table_name'. TDBM usually assumes that the primary key is automatically set by the DB engine to the maximum value in the database. However, in this case, the '$this->db_table_name' table has a primary key on multiple columns. TDBM would be unable to find back this record after save. Please specify the primary keys for all new objects of kind '$this->db_table_name'.";

				if (!$this->tdbmService->isProgramExiting())
				throw new TDBMException($msg);
				else
				trigger_error($msg, E_USER_ERROR);
			}
			
			$sql = 'INSERT INTO '.$this->db_connection->escapeDBItem($this->db_table_name).
					' ('.implode(",", array_map(array($this->db_connection, "escapeDBItem"), array_keys($this->db_row))).')
					 VALUES (';

			$first = true;
			foreach ($this->db_row as $key=>$value) {
				if (!$first)
				$sql .= ',';
				$sql .= $this->db_connection->quoteSmart($value);
				$first=false;
			}
			$sql .= ')';

			try {
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
			}

			// Let's remove this object from the $new_objects static table.
			$this->tdbmService->_removeFromToSaveObjectList($this);

			// If there is only one column for the primary key, and if it has not been filled, let's find it.
			// We assume this is the biggest ID in the database
			if (count($pk_array)==1 && !$pk_set) {
				$this->TDBMObject_id = $this->db_connection->getInsertId($this->db_table_name,$pk_array[0]);
				$this->db_row[$pk_array[0]] = $this->TDBMObject_id;
			} elseif (count($pk_array)==1 && $pk_set) {
				$this->TDBMObject_id = $this->db_row[$pk_array[0]];
			}

			// Ok, now let's get the primary key
			/*$primary_key = $this->getPrimaryKey();

			if (!isset($this->db_row[$primary_key])) {
			$this->TDBMObject_id = $this->db_connection->getInsertId($this->db_table_name,$primary_key);
			$this->db_row[$primary_key] = $this->TDBMObject_id;
			}*/

			// Maybe some default values have been set.
			// Therefore, we must reload the object if required.
			/*$new_db_row = array();
			foreach ($pk_array as $pk) {
				$new_db_row[$pk] = $this->db_row[$pk];
			}
			var_dump($pk_array);
			var_dump($new_db_row);*/
			
			$this->TDBMObject_state = "not loaded";
			$this->db_modified_state = false;
			$this->db_row = null;
			
			// Let's add this object to the list of objects in cache.
			$this->tdbmService->_addToCache($this);
		} else if ($this->TDBMObject_state == "loaded" && $this->db_modified_state==true) {
			//$primary_key = $this->getPrimaryKey();
			// Let's first get the primary keys
			$pk_table = $this->getPrimaryKey();
			// Now for the object_id
			$object_id = $this->TDBMObject_id;
			// If there is only one primary key:
			if (count($pk_table)==1) {
				$sql_where = $this->db_connection->escapeDBItem($pk_table[0])."=".$this->db_connection->quoteSmart($this->TDBMObject_id);
			} else {
				$ids = unserialize($object_id);
				$i=0;
				$sql_where_array = array();
				foreach ($pk_table as $pk) {
					$sql_where_array[] = $this->db_connection->escapeDBItem($pk)."=".$this->db_connection->quoteSmart($ids[$i]);
					$i++;
				}
				$sql_where = implode(" AND ",$sql_where_array);
			}

			$sql = 'UPDATE '.$this->db_connection->escapeDBItem($this->db_table_name).' SET ';

			$first = true;
			foreach ($this->db_row as $key=>$value) {
				if (!$first)
				$sql .= ',';
				$sql .= $this->db_connection->escapeDBItem($key)." = ".$this->db_connection->quoteSmart($value);
				$first=false;
			}
			$sql .= ' WHERE '.$sql_where/*$primary_key."='".$this->db_row[$primary_key]."'"*/;
			try {
				$this->db_connection->exec($sql);
			} catch (TDBMException $e) {
				if (!$this->tdbmService->isProgramExiting())
				throw $e;
				else
				trigger_error($e->getMessage(), E_USER_ERROR);
			}

			// Let's remove this object from the $new_objects static table.
			$this->tdbmService->_removeFromToSaveObjectList($this);
			
			$this->db_modified_state = false;
		}
	}

	function __destruct() {
		// In a destructor, no exception can be thrown (PHP 5 limitation)
		// So we print the error instead
		try {
			if (!$this->db_onerror && $this->db_autosave)
			{
				$this->save();
			}
		} catch (Exception $e) {
			//echo($e->getMessage());
			trigger_error($e->getMessage(), E_USER_ERROR);
		}
	}


	/**
	 * Reverts any changes made to the object and resumes it to its DB state.
	 * This can only be called on objects that come from database adn that have not been deleted.
	 * Otherwise, this will throw an exception.
	 *
	 */
	public function discardChanges() {
		if ($this->TDBMObject_state == "new")
		throw new TDBMException("You cannot call discardChanges() on an object that has been created with getNewObject and that has not yet been saved.");

		if ($this->TDBMObject_state == "deleted")
		throw new TDBMException("You cannot call discardChanges() on an object that has been deleted.");
			
		$this->db_modified_state = false;
		$this->TDBMObject_state = "not loaded";
	}



	/**
	 * Used to implement the get_XXX functions where XXX is a table name.
	 *
	 * @param unknown_type $func_name
	 * @param unknown_type $values
	 * @return unknown
	 */
	public function __call($func_name, $values) {

		if (strpos($func_name,"get_") === 0) {
			$table = substr($func_name,4);
		} else {
			throw new TDBMException("Method ".$func_name." not found");
		}

		//return $this->cleverget($table, $values[0]);
		return $this->tdbmService->getObjects($table, $this, null, null, null, $values[0]);
	}

	/**
	 * Returns the name of the table this object comes from.
	 * 
	 * @return string
	 */
	public function _getDbTableName() {
		return $this->db_table_name;
	}
	
	/**
	 * Method used internally by TDBM. You should not use it directly.
	 * This method returns the status of the TDBMObject.
	 * This is one of "new", "not loaded", "loaded", "deleted".
	 * $TDBMObject_state = "new" when a new object is created with DBMObject:getNewObject.
	 * $TDBMObject_state = "not loaded" when the object has been retrieved with getObject but when no data has been accessed in it yet.
	 * $TDBMObject_state = "loaded" when the object is cached in memory.
	 *
	 * @return string
	 */
	public function _getStatus() {
		return $this->TDBMObject_state;
	}
	
		/**
	 * Implements array behaviour for our object.
	 * 
	 * @param string $offset
	 * @param string $value
	 */
	public function offsetSet($offset, $value) {
		$this->__set($offset, $value);
    }
	/**
	 * Implements array behaviour for our object.
	 * 
	 * @param string $offset
	 */
    public function offsetExists($offset) {
    	$this->_dbLoadIfNotLoaded();
        return isset($this->db_row[$offset]);
    }
	/**
	 * Implements array behaviour for our object.
	 * 
	 * @param string $offset
	 */
    public function offsetUnset($offset) {
		$this->__set($offset, null);
    }
	/**
	 * Implements array behaviour for our object.
	 * 
	 * @param string $offset
	 */
    public function offsetGet($offset) {
        return $this->__get($offset);
    }
	
	private $_validIterator = false;
	/**
	 * Implements iterator behaviour for our object (so we can each column).
	 */
	public function rewind() {
    	$this->_dbLoadIfNotLoaded();
		if (count($this->db_row)>0) {
			$this->_validIterator = true;
		} else {
			$this->_validIterator = false;
		}
		reset($this->db_row);
	}
	/**
	 * Implements iterator behaviour for our object (so we can each column).
	 */
	public function next() {
		$val = next($this->db_row);
		$this->_validIterator = !($val === false);
	}
	/**
	 * Implements iterator behaviour for our object (so we can each column).
	 */
	public function key() {
		return key($this->db_row);
	}
	/**
	 * Implements iterator behaviour for our object (so we can each column).
	 */
	public function current() {
		return current($this->db_row);
	}
	/**
	 * Implements iterator behaviour for our object (so we can each column).
	 */
	public function valid() {
		return $this->_validIterator;
	}	
}


?>