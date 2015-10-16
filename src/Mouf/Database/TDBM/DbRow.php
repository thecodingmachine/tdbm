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
 * Instances of this class represent a row in a database.
 *
 * @author David Negrier
 */
class DbRow implements \JsonSerializable, FilterInterface {

	/**
	 * The service this object is bound to.
	 * 
	 * @var TDBMService
	 */
	protected $tdbmService;

	/**
	 * The object containing this db row.
	 * @var AbstractTDBMObject
	 */
	private $object;

	/**
	 * The name of the table the object if issued from
	 *
	 * @var string
	 */
	private $dbTableName;

	/**
	 * The array of columns returned from database.
	 *
	 * @var array
	 */
	private $dbRow = array();

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
	 * This is set when the object is in "loaded" state.
	 *
	 * @var array An array of column => value
	 */
	private $primaryKeys;


	private $db_connection;

	/**
	 * You should never call the constructor directly. Instead, you should use the
	 * TDBMService class that will create TDBMObjects for you.
	 *
	 * Used with id!=false when we want to retrieve an existing object
	 * and id==false if we want a new object
	 *
	 * @param AbstractTDBMObject $object The object containing this db row.
	 * @param string $table_name
	 * @param array $primaryKeys
	 * @param TDBMService $tdbmService
	 * @throws TDBMException
	 * @throws TDBMInvalidOperationException
	 */
	public function __construct(AbstractTDBMObject $object, $table_name, array $primaryKeys=array(), TDBMService $tdbmService=null) {
		$this->object = $object;
		$this->dbTableName = $table_name;

		if ($tdbmService === null) {
			$this->status = TDBMObjectStateEnum::STATE_DETACHED;
			if (!empty($primaryKeys)) {
				throw new TDBMException('You cannot pass an id to the DbRow constructor without passing also a TDBMService.');
			}
		} else {
			$this->_attach($tdbmService);

			if (!empty($primaryKeys)) {
				$this->_setPrimaryKeys($primaryKeys);
				$this->status = TDBMObjectStateEnum::STATE_NOT_LOADED;
			} else {
				$this->status = TDBMObjectStateEnum::STATE_NEW;
			}
		}
	}

	public function _attach(TDBMService $tdbmService) {
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
	 * $status = TDBMObjectStateEnum::STATE_NEW when a new object is created with DBMObject:getNewObject.
	 * $status = TDBMObjectStateEnum::STATE_NOT_LOADED when the object has been retrieved with getObject but when no data has been accessed in it yet.
	 * $status = TDBMObjectStateEnum::STATE_LOADED when the object is cached in memory.
	 * @param string $state
	 */
	public function _setStatus($state){
		$this->status = $state;
	}

    /**
     * Internal TDBM method, you should not use this.
     * Loads the dbRow property of the object from the $row array.
     * Any row having a key starting with 'tdbm_reserved_col_' is ignored.
     *
     * @param array $row
     * @param array|null $colsArray A big optimization to avoid calling strpos to many times. This array should
     *                              contain as keys the list of interesting columns. If null, this list will be initialized.
     */
	public function loadFromRow($row, &$colsArray) {
        if ($colsArray === null) {
            foreach ($row as $key=>$value) {
                if (strpos($key, 'tdbm_reserved_col_')!==0) {
                    $colsArray[$key] = true;
                }
            }
        }

        $this->dbRow = array_intersect_key($row, $colsArray);
        $this->status = TDBMObjectStateEnum::STATE_LOADED;
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
		return $this->tdbmService->getPrimaryKeyColumns($this->dbTableName);
	}

	/**
	 * This is an internal method. You should not call this method yourself. The TDBM library will do it for you.
	 * If the object is in state 'not loaded', this method performs a query in database to load the object.
	 *
	 * A TDBMException is thrown is no object can be retrieved (for instance, if the primary key specified
	 * cannot be found).
	 */
	public function _dbLoadIfNotLoaded() {
		if ($this->status == TDBMObjectStateEnum::STATE_NOT_LOADED)
		{
			$sql_where = $this->getPrimaryKeyWhereStatement();

			$sql = "SELECT * FROM ".$this->db_connection->escapeDBItem($this->dbTableName)." WHERE ".$sql_where;
			$result = $this->db_connection->query($sql);


			if ($result->rowCount()==0)
			{
				throw new TDBMException("Could not retrieve object from table \"$this->dbTableName\" with ID \"".$this->TDBMObject_id."\".");
			}

			$fullCaseRow = $result->fetchAll(\PDO::FETCH_ASSOC);
			
			$result->closeCursor();
				
			$this->dbRow = array();
			foreach ($fullCaseRow[0] as $key=>$value)  {
				$this->dbRow[$this->db_connection->toStandardCaseColumn($key)]=$value;
			}
			
			$this->status = TDBMObjectStateEnum::STATE_LOADED;
		}
	}

	public function get($var) {
		$this->_dbLoadIfNotLoaded();

		// Let's first check if the key exist.
		if (!isset($this->dbRow[$var])) {
			/*
			// Unable to find column.... this is an error if the object has been retrieved from database.
			// If it's a new object, well, that may not be an error after all!
			// Let's check if the column does exist in the table
			$column_exist = $this->db_connection->checkColumnExist($this->dbTableName, $var);
			// If the column DOES exist, then the object is new, and therefore, we should
			// return null.
			if ($column_exist === true) {
				return null;
			}

			// Let's try to be accurate in error reporting. The checkColumnExist returns an array of closest matches.
			$result_array = $column_exist;

			if (count($result_array)==1)
			$str = "Could not find column \"$var\" in table \"$this->dbTableName\". Maybe you meant this column: '".$result_array[0]."'";
			else
			$str = "Could not find column \"$var\" in table \"$this->dbTableName\". Maybe you meant one of those columns: '".implode("', '",$result_array)."'";

			throw new TDBMException($str);*/
			return null;
		}
		return $this->dbRow[$var];
	}

	/**
	 * Returns true if a column is set, false otherwise.
	 * 
	 * @param string $var
	 * @return boolean
	 */
	public function has($var) {
		$this->_dbLoadIfNotLoaded();

		return isset($this->dbRow[$var]);
	}
	
	public function set($var, $value) {
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
		if ($this->tdbmService !== null && $this->status === TDBMObjectStateEnum::STATE_LOADED) {
			$this->status = TDBMObjectStateEnum::STATE_DIRTY;
			$this->tdbmService->_addToToSaveObjectList($this);
		}
		// Unset the error since something has changed (Insert or Update could work this time).
		$this->db_onerror = false;
	}

	/**
	 * Returns the name of the table this object comes from.
	 * 
	 * @return string
	 */
	public function _getDbTableName() {
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
	public function _getStatus() {
		return $this->status;
	}

	
	/**
	 * Implement the unique JsonSerializable method
	 * @return array
	 */
	public function jsonSerialize(){
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
		return array($this->dbTableName);
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
	 * Returns raw database row.
	 *
	 * @return array
	 */
	public function _getDbRow() {
		return $this->dbRow;
	}

	/**
	 * @return array
	 */
	public function _getPrimaryKeys()
	{
		return $this->primaryKeys;
	}

	/**
	 * @param array $primaryKeys
	 */
	public function _setPrimaryKeys(array $primaryKeys)
	{
		$this->primaryKeys = $primaryKeys;
		foreach ($this->primaryKeys as $column => $value) {
			$this->dbRow[$column] = $value;
		}
	}


}