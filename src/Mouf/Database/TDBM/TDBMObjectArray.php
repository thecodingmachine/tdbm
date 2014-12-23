<?php
namespace Mouf\Database\TDBM;

use Mouf\Database\DBConnection\ConnectionInterface;
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
 * An object that behaves just like an array of TDBMObjects.
 * If there is only one object in it, it can be accessed just like an object.
 *
 */
class TDBMObjectArray extends \ArrayObject implements \JsonSerializable {
	
	const MODE_CURSOR = 1;
	const MODE_ARRAY = 2;
	
	private $statement;
	private $fetchStarted = false;
	private $keysStandardCased = array();
	private $dbConnection;
	private $table_name;
	private $objectStorage;
	private $className;
	private $primary_keys;
	private $tdbmService;
	private $sql;
	private $mode = self::MODE_ARRAY;
	
	public function __construct(\PDOStatement $statement, ConnectionInterface $dbConnection, $primary_keys, $table_name, $objectStorage, $className, TDBMService $tdbmService, $sql) {
		$this->statement = $statement;
		$this->dbConnection = $dbConnection;
		$this->table_name = $table_name;
		$this->primary_keys = $primary_keys;
		$this->objectStorage = $objectStorage;
		$this->className = $className;
		$this->tdbmService = $tdbmService;
		$this->sql = $sql;
	}
	
	/**
	 * Sets the fetch mode of the array.
	 * Can be one of: TDBMObjectArray::MODE_CURSOR or TDBMObjectArray::MODE_ARRAY
	 * 
	 * In 'MODE_CURSOR' mode, the TDBMObjectArray can be scanned only once (only one "foreach") on it,
	 * and it cannot be accessed via key. Use this mode for large datasets processed by batch.
	 * In 'MODE_ARRAY' mode, the TDBMObjectArray can be used as an array. You can access the array by key,
	 * or using foreach, several times. Use this mode by default (unless the list returned is very big).
	 *  
	 * @param int $mode
	 */
	public function setMode($mode) {
		$this->mode = $mode;
		return $this;
	}
	
	public function offsetGet($offset) {
		if ($this->mode == self::MODE_CURSOR) {
			throw new TDBMException("Cannot access a TDBMObjectArray by key in CURSOR mode.");	
		}
		
		if (parent::offsetExists($offset)) {
			return parent::offsetGet($offset);
		}
		if (!is_numeric($offset)) {
			throw new TDBMException("The offset in a TDBM list must be an integer. Value passed: '".$offset."'");
		}
		$dataSetCount = $this->statement->rowCount();
		if ($offset >= $dataSetCount) {
			throw new TDBMException("No offset ".$offset."' in TDBM list. The list contains only '".$dataSetCount."' value(s).");
		}
		while (parent::count() < $dataSetCount) {
			$this->fetchObject();
		}
		return parent::offsetGet($offset);
	}
	
	private function fetchObject() {
		if (!$this->statement) {
			return null;
		}
		$fullCaseRow = $this->statement->fetch(\PDO::FETCH_ASSOC);
		if (!$fullCaseRow) {
			$this->statement->closeCursor();
			$this->statement = null;
			return null;
		}
		
		$row = array();
			
		if ($this->fetchStarted === false) {
			// $keysStandardCased is an optimization to avoid calling toStandardCaseColumn on every cell of every row.
			foreach ($fullCaseRow as $key=>$value)  {
				$this->keysStandardCased[$key] = $this->dbConnection->toStandardCaseColumn($key);
			}
			$this->fetchStarted = true;
		}
		foreach ($fullCaseRow as $key=>$value)  {
			$row[$this->keysStandardCased[$key]]=$value;
		}
	
		if (count($this->primary_keys)==1)
		{
			if (!isset($this->keysStandardCased[$this->primary_keys[0]])) {
				throw new TDBMException("Bad SQL request passed to getObjectsFromSQL. The SQL request should return all the rows from the '".$this->table_name."' table. Could not find primary key in this set of rows. SQL request passed: ".$this->sql);
			}
			$id = $row[$this->keysStandardCased[$this->primary_keys[0]]];
		}
		else
		{
			// Let's generate the serialized primary key from the columns!
			$ids = array();
			foreach ($this->primary_keys as $pk) {
				$ids[] = $row[$this->keysStandardCased[$pk]];
			}
			$id = serialize($ids);
		}
	
		$obj = $this->objectStorage->get($this->table_name, $id);
		if ($obj === null)
		{
			if ($this->className == null) {
				$obj = new TDBMObject($this->tdbmService, $this->table_name, $id);
			} elseif (is_string($this->className)) {
				if (!is_subclass_of($this->className, "Mouf\\Database\\TDBM\\TDBMObject")) {
					if (class_exists($this->className)) {
						throw new TDBMException("Error while calling TDBM: The class ".$this->className." should extend TDBMObject.");
					} else {
						throw new TDBMException("Error while calling TDBM: The class ".$this->className." does not exist or could not be loaded.");
					}
				}
				$obj = new $this->className($this->tdbmService, $this->table_name, $id);
			} else {
				throw new TDBMException("Error while casting TDBMObject to class, the parameter passed is not a string. Value passed: ".$this->className);
			}
			$this->objectStorage->set($this->table_name, $id, $obj);
			$obj->loadFromRow($row);
		} elseif ($obj->_getStatus() == "not loaded") {
			$obj->loadFromRow($row);
			// Check that the object fetched from cache is from the requested class.
			if ($this->className != null) {
				if (!is_subclass_of(get_class($obj), $this->className) &&  get_class($obj) != $this->className) {
					throw new TDBMException("Error while calling TDBM: An object fetched from database is already present in TDBM cache and they do not share the same class. You requested the object to be of the class ".$this->className." but the object available locally is of the class ".get_class($obj).".");
				}
			}
		} else {
			// Check that the object fetched from cache is from the requested class.
			if ($this->className != null) {
				$this->className = ltrim($this->className, '\\');
				if (!is_subclass_of(get_class($obj), $this->className) &&  get_class($obj) != $this->className) {
					throw new TDBMException("Error while calling TDBM: An object fetched from database is already present in TDBM cache and they do not share the same class. You requested the object to be of the class ".$this->className." but the object available locally is of the class ".get_class($obj).".");
				}
			}
		}
		$this[] = $obj;
		
		return $obj;
	}
	
	protected $position = 0;
	protected $current;
	
	public function getIterator() {
		if ($this->mode == self::MODE_CURSOR) {
			return new TDBMObjectArrayCursorIterator($this->statement, $this->dbConnection, 
					$this->primary_keys, $this->table_name, $this->objectStorage, $this->className, 
					$this->tdbmService, $this->sql);
		} else {
			// On first call, let's fill the complete array.
			while ($this->fetchObject()) {
				// Loop until all objects are fetched.
			}
			
			return parent::getIterator();
		}
	}
	
	
	
	
	public function __get($var) {
		$cnt = count($this);
		if ($cnt==1)
		{
			return $this[0]->__get($var);
		}
		elseif ($cnt>1)
		{
			throw new TDBMException('Array contains many objects! Use getarray_'.$var.' to retrieve an array of '.$var);
		}
		else
		{
			throw new TDBMException('Array contains no objects');
		}
	}

	public function __set($var, $value) {
		$cnt = count($this);
		if ($cnt==1)
		{
			return $this[0]->__set($var, $value);
		}
		elseif ($cnt>1)
		{
			throw new TDBMException('Array contains many objects! Use setarray_'.$var.' to set the array of '.$var);
		}
		else
		{
			throw new TDBMException('Array contains no objects');
		}
	}

	/**
	 * getarray_column_name returns an array containing the values of the column of the given objects.
	 * setarray_column_name sets the value of the given column for all the objects.
	 *
	 * @param unknown_type $func_name
	 * @param unknown_type $values
	 * @return unknown
	 */
	public function __call($func_name, $values) {

		if (strpos($func_name,"getarray_") === 0) {
			$column = substr($func_name, 9);
			return $this->getarray($column);
		} elseif (strpos($func_name,"setarray_") === 0) {
			$column = substr($func_name, 9);
			return $this->setarray($column, $values[0]);
		} elseif (count($this)==1) {
			$this[0]->__call($func_name, $values);
		}
		else
		{
			throw new TDBMException("Method ".$func_name." not found");
		}

	}

	private function getarray($column) {
		$arr = array();
		foreach ($this as $object) {
			$arr[] = $object->__get($column);
		}
		return $arr;
	}

	private function setarray($column, $value) {
		foreach ($this as $object) {
			$object->__set($column, $value);
		}
	}
	
	public function jsonSerialize(){
		return (array) $this;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ArrayObject::count()
	 */
	public function count(){
		return $this->statement->rowCount();
	}
	

}
