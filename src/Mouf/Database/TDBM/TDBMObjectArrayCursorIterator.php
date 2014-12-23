<?php
namespace Mouf\Database\TDBM;

use Mouf\Database\DBConnection\ConnectionInterface;
/*
 Copyright (C) 2006-2014 David Négrier - THE CODING MACHINE

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
 * Iterator used for TDBMObjectArrays in CURSOR mode.
 *
 */
class TDBMObjectArrayCursorIterator implements \Iterator, \Countable {
	
	
	
	/**
	 *
	 * @var PDOStatement
	 */
	protected $pdoStatement;
	
	private $fetchStarted = false;
	private $keysStandardCased = array();
	private $dbConnection;
	private $table_name;
	private $objectStorage;
	private $className;
	private $primary_keys;
	private $tdbmService;
	private $sql;
	
	/**
	 *
	 * @var int
	 */
	protected $cursor = -1;
	/**
	 *
	 * @var array
	 */
	protected $current = false;
	
	public function __construct(\PDOStatement $pdoStatement, ConnectionInterface $dbConnection, $primary_keys, $table_name, $objectStorage, $className, TDBMService $tdbmService, $sql)
	{
		$this->pdoStatement = $pdoStatement;
		$this->dbConnection = $dbConnection;
		$this->table_name = $table_name;
		$this->primary_keys = $primary_keys;
		$this->objectStorage = $objectStorage;
		$this->className = $className;
		$this->tdbmService = $tdbmService;
		$this->sql = $sql;
	}
	
	/**
	 * Casts a document to a new instance specified in the $recordClass
	 * @param array $item
	 * @return Record_Abstract|false
	 */
	protected function cast($fullCaseRow)
	{
		if (!is_array($fullCaseRow)) {
			return false;
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
				$obj = new TDBMObject($this, $this->table_name, $id);
			} elseif (is_string($this->className)) {
				if (!is_subclass_of($this->className, "Mouf\\Database\\TDBM\\TDBMObject")) {
					throw new TDBMException("Error while calling TDBM: The class ".$this->className." should extend TDBMObject.");
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
		
		return $obj;
	}
	
	/**
	 * Counts found records
	 * @return int
	 */
	public function count()
	{
		return $this->pdoStatement->rowCount();
	}
	/**
	 * Fetches record at current cursor. Alias for current()
	 * @return Record_PDO|false
	 */
	public function current()
	{
		return $this->current ? $this->cast($this->current) : false;
	}
	/**
	 * Returns the current result's _id
	 * @return string The current result's _id as a string.
	 */
	public function key()
	{
		return $this->cursor;
	}
	/**
	 * Advances the cursor to the next result
	 * @return boolean
	 */
	public function next()
	{
		if ($this->hasNext()) {
			$this->cursor++;
			$this->current = $this->pdoStatement->fetch(\PDO::FETCH_ASSOC);
			if (empty($this->current))
				$this->current = false;
			else
				return true;
		}else
			$this->current = false;
		return false;
	}
	
	/**
	 * Moves the cursor to the beginning of the result set
	 */
	public function rewind()
	{
		if ($this->cursor != -1) {
			$this->cursor = -1;
			$this->pdoStatement->execute();
		} else {
			$this->next();
		}
	}
	/**
	 * Checks if the cursor is reading a valid result.
	 *
	 * @return boolean
	 */
	public function valid()
	{
		return ($this->current != false) || (($this->cursor == -1) && ($this->count() > 0));
	}
	/**
	 * Fetches first record and rewinds the cursor
	 * @return Record_PDO|false
	 */
	public function getFirst()
	{
		$this->rewind();
		return $this->getNext();
	}
	/**
	 *
	 * @return boolean
	 */
	public function hasNext()
	{
		return ($this->count() > 0) && (($this->cursor + 1) < $this->count());
	}
	/**
	 * Return the next record to which this cursor points, and advance the cursor
	 * @return Record_PDO|false Next record or false if there's no more records
	 */
	public function getNext()
	{
		if ($this->next()) {
			return $this->cast($this->current);
		} else {
			$this->rewind();
		}
		return false;
	}
	/**
	 * Fetches the record at current cursor. Alias for current()
	 * @return Record_PDO|false
	 */
	public function getCurrent()
	{
		return $this->current();
	}
	/**
	 * Fetches all records (this could impact into your site performance) and rewinds the cursor
	 * @param boolean $asRecords Bind into record class?
	 * @return array[Record_PDO|array] Array of records or arrays (depends on $asRecords)
	 */
	public function getAll($asRecords = true)
	{
		$all = array();
		$this->rewind();
		foreach ($this->pdoStatement as $id => $doc) {
			if ($asRecords)
				$all[$id] = $this->cast($doc);
			else
				$all[$id] = $doc;
		}
		return $all;
	}
	/**
	 *
	 * @return PDOStatement
	 */
	public function getPDOStatement()
	{
		return $this->pdoStatement;
	}
	
	

}
