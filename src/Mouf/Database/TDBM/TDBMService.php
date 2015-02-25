<?php
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

namespace Mouf\Database\TDBM;

use Mouf\Database\TDBM\Filters\InFilter;
use Mouf\Database\TDBM\Filters\OrderBySQLString;
use Mouf\Database\TDBM\Filters\EqualFilter;
use Mouf\Database\TDBM\Filters\SqlStringFilter;
use Mouf\Database\TDBM\Filters\AndFilter;
use Mouf\Database\DBConnection\CachedConnection;
use Mouf\Utils\Cache\CacheInterface;
use Mouf\Database\TDBM\Filters\FilterInterface;
use Mouf\Database\DBConnection\ConnectionInterface;
use Mouf\Database\DBConnection\DBConnectionException;
use Mouf\Database\TDBM\Filters\OrFilter;
use Mouf\Database\TDBM\Utils\TDBMDaoGenerator;

/**
 * The TDBMService class is the main TDBM class. It provides methods to retrieve TDBMObject instances
 * from the database.
 *
 * @author David Negrier
 * @ExtendedAction {"name":"Generate DAOs", "url":"tdbmadmin/", "default":false}
 */
class TDBMService {
	
	const MODE_CURSOR = 1;
	const MODE_ARRAY = 2;
	const MODE_COMPATIBLE_ARRAY = 3;
	
	/**
	 * The database connection.
	 *
	 * @var ConnectionInterface
	 */
	public $dbConnection;

	/**
	 * The cache service to cache data.
	 *
	 * @var CacheInterface
	 */
	public $cacheService;

	/**
	 * The default autosave mode for the objects
	 * True to automatically save the object.
	 * If false, the user must explicitly call the save() method to save the object.
	 *
	 * @var boolean
	 */
	private $autosave_default = true;

	/**
	 * If TDBM objects are modified, and if they are not saved, they will automatically be saved at the end of the script.
	 * Of course, if a transaction has been started, and is not ended, at the end of the script, it is likely that the
	 * transaction will roll-back and that the changes will be lost.
	 * If commitOnQuit is set to "true", a commit will always be performed at the end of the script.
	 * This is a dangerous parameter. Indeed, in case of error, it might commit data that would have otherwised been roll-back.
	 * Use it sparesly.
	 *
	 * @var boolean
	 */
	private $commitOnQuit = false;

	private $table_descs;

	/**
	 * Cache of table of primary keys.
	 * Primary keys are stored by tables, as an array of column.
	 * For instance $primary_key['my_table'][0] will return the first column of the primary key of table 'my_table'.
	 *
	 * @var array
	 */
	private $primary_keys;

	/**
	 * Whether we should track execution time or not.
	 * If true, if the execution time reaches 90% of the allowed execution time, the request will stop with an exception.
	 *
	 * @var bool
	 */
	private $trackExecutionTime = true;

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
	 * Can be one of: TDBMObjectArray::MODE_CURSOR or TDBMObjectArray::MODE_ARRAY or TDBMObjectArray::MODE_COMPATIBLE_ARRAY
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

	/// Table of new objects not yet inserted in database or objects modified that must be saved.
	private $tosave_objects;

	/// Table of constraints that that table applies on another table n the form [this table][this column]=XXX
	//private $external_constraint;

	/// The timestamp of the script startup. Useful to stop execution before time limit is reached and display useful error message.
	public static $script_start_up_time;

	/// True if the program is exiting (we are in the "exit" statement). False otherwise.
	private $is_program_exiting = false;

	/**
	 * The content of the cache variable.
	 *
	 * @var array<string, mixed>
	 */
	private $cache;

	private $cacheKey = "__TDBM_Cache__";
	
	public function __construct() {
		register_shutdown_function(array($this,"completeSaveOnExit"));
		if (extension_loaded('weakref')) {
			$this->objectStorage = new WeakrefObjectStorage();
		} else {
			$this->objectStorage = new StandardObjectStorage();
		}
	}

	/**
	 * Sets up the default connection to the database.
	 * The parameters of TDBMService::setConnection are similar to the parameters used by PEAR DB (since TDBMObject relies on PEAR DB).
	 * TODO: CORRECT THE DOC!!!!
	 * For instance:
	 * TDBMObject::setConnection(array(
	 *    'phptype'  => 'pgsql',
	 *    'username' => 'my_user',
	 *    'password' => 'my_password',
	 *    'hostspec' => 'ip_of_my_database_server',
	 *    'database' => 'name_of_my_base'
	 * ));
	 *
	 * where phptype is the type of database supported (currently can be only 'pgsql' for PostGreSQL)
	 *       username is the name of your database user
	 *       password is the password of your rdatabase user
	 *       hostspec is the IP of your database server (very likely, it will be 'localhost' for you)
	 *       database is the name of your database
	 *
	 * @Compulsory
	 * @param ConnectionInterface $connection
	 */
	public function setConnection(ConnectionInterface $connection) {
		if ($this->cacheService != null && !($connection instanceof CachedConnection)) {
			$cachedConnection = new CachedConnection();
			$cachedConnection->dbConnection = $connection;
			$cachedConnection->cacheService = $this->cacheService;
			$this->dbConnection = $cachedConnection;
		} else {
			$this->dbConnection = $connection;
		}
	}

	/**
	 * Returns the object used to connect to the database.
	 *
	 * @return ConnectionInterface
	 */
	public function getConnection() {
		return $this->dbConnection;
	}

	/**
	 * Sets the cache service.
	 * The cache service is used to store the structure of the database in cache, which will dramatically improve performances.
	 * The cache service will also wrap the database connection into a cached connection.
	 *
	 * @Compulsory
	 * @param CacheInterface $cacheService
	 */
	public function setCacheService(CacheInterface $cacheService) {
		$this->cacheService = $cacheService;
		if ($this->dbConnection != null && !($this->dbConnection instanceof CachedConnection)) {
			$cachedConnection = new CachedConnection();
			$cachedConnection->dbConnection = $this->dbConnection;
			$cachedConnection->cacheService = $this->cacheService;
			$this->dbConnection = $cachedConnection;
		}
	}

	/**
	 * Returns true if the objects will save automatically by default,
	 * false if an explicit call to save() is required.
	 *
	 * The behaviour can be overloaded by setAutoSaveMode on each object.
	 *
	 * @return boolean
	 */
	public function getDefaultAutoSaveMode() {
		return $this->autosave_default;
	}

	/**
	 * Sets the autosave mode:
	 * true if the object will save automatically,
	 * false if an explicit call to save() is required.
	 *
	 * @Compulsory
	 * @param boolean $autoSave
	 */
	public function setDefaultAutoSaveMode($autoSave = true) {
		$this->autosave_default = $autoSave;
	}


	/**
	 * If TDBM objects are modified, and if they are not saved, they will automatically be saved at the end of the script.
	 * Of course, if a transaction has been started, and is not ended, at the end of the script, it is likely that the
	 * transaction will roll-back and that the changes will be lost.
	 * If commitOnQuit is set to "true", a commit will always be performed at the end of the script.
	 * This is a dangerous parameter. Indeed, in case of error, it might commit data that would have otherwised been roll-back.
	 * Use it sparesly.
	 *
	 * @Compulsory
	 * @param boolean $commitOnQuit
	 */
	public function setCommitOnQuit($commitOnQuit) {
		$this->commitOnQuit = $commitOnQuit;
	}
	
	/**
	 * Sets the fetch mode of the result sets returned by `getObjects`.
	 * Can be one of: TDBMObjectArray::MODE_CURSOR or TDBMObjectArray::MODE_ARRAY or TDBMObjectArray::MODE_COMPATIBLE_ARRAY
	 *
	 * In 'MODE_ARRAY' mode (default), the result is an array. Use this mode by default (unless the list returned is very big).
	 * In 'MODE_CURSOR' mode, the result is an iterable collection that can be scanned only once (only one "foreach") on it,
	 * and it cannot be accessed via key. Use this mode for large datasets processed by batch.
	 * In 'MODE_COMPATIBLE_ARRAY' mode, the result is an old TDBMObjectArray (used up to TDBM 3.2). 
	 * You can access the array by key, or using foreach, several times.
	 *
	 * @param int $mode
	 */
	public function setFetchMode($mode) {
		$this->mode = $mode;
		return $this;
	}

	/**
	 * Whether we should track execution time or not.
	 * If true, if the execution time reaches 90% of the allowed execution time, the request will stop with an exception.
	 *
	 * @param boolean $trackExecutionTime
	 */
	public function setTrackExecutionTime($trackExecutionTime = true) {
		$this->trackExecutionTime = $trackExecutionTime;
	}


	/**
	 * Loads the cache and stores it (to be reused in this instance).
	 * Note: the cache is not returned. It is stored in the $cache instance variable.
	 */
	private function loadCache() {
		if ($this->cache == null) {
			if ($this->cacheService == null) {
				throw new TDBMException("A cache service must be explicitly bound to the TDBM Service. Please configure your instance of TDBM Service.");
			}
			$this->cache = $this->cacheService->get($this->cacheKey);
		}
	}

	/**
	 * Saves the cache.
	 *
	 */
	private function saveCache() {
		$this->cacheService->set($this->cacheKey, $this->cache);
	}

	/**
	 * Returns a TDBMObject associated from table "$table_name".
	 * If the $filters parameter is an int/string, the object returned will be the object whose primary key = $filters.
	 * $filters can also be a set of TDBM_Filters (see the getObjects method for more details).
	 *
	 * For instance, if there is a table 'users', with a primary key on column 'user_id' and a column 'user_name', then
	 * 			$user = $tdbmService->getObject('users',1);
	 * 			echo $user->name;
	 * will return the name of the user whose user_id is one.
	 *
	 * If a table has a primary key over several columns, you should pass to $id an array containing the the value of the various columns.
	 * For instance:
	 * 			$group = $tdbmService->getObject('groups',array(1,2));
	 *
	 * Note that TDBMObject performs caching for you. If you get twice the same object, the reference of the object you will get
	 * will be the same.
	 *
	 * For instance:
	 * 			$user1 = $tdbmService->getObject('users',1);
	 * 			$user2 = $tdbmService->getObject('users',1);
	 * 			$user1->name = 'John Doe';
	 * 			echo $user2->name;
	 * will return 'John Doe'.
	 *
	 * You can use filters instead of passing the primary key. For instance:
	 * 			$user = $tdbmService->getObject('users',new EqualFilter('users', 'login', 'jdoe'));
	 * This will return the user whose login is 'jdoe'.
	 * Please note that if 2 users have the jdoe login in database, the method will throw a TDBM_DuplicateRowException.
	 *
	 * Also, you can specify the return class for the object (provided the return class extends TDBMObject).
	 * For instance:
	 *  	$user = $tdbmService->getObject('users',1,'User');
	 * will return an object from the "User" class. The "User" class must extend the "TDBMObject" class.
	 * Please be sure not to override any method or any property unless you perfectly know what you are doing!
	 *
	 * @param string $table_name The name of the table we retrieve an object from.
	 * @param mixed $filters If the filter is a string/integer, it will be considered as the id of the object (the value of the primary key). Otherwise, it can be a filter bag (see the filterbag parameter of the getObjects method for more details about filter bags)
	 * @param string $className Optional: The name of the class to instanciate. This class must extend the TDBMObject class. If none is specified, a TDBMObject instance will be returned.
	 * @param boolean $lazy_loading If set to true, and if the primary key is passed in parameter of getObject, the object will not be queried in database. It will be queried when you first try to access a column. If at that time the object cannot be found in database, an exception will be thrown.
	 * @return TDBMObject
	 */
	public function getObject($table_name, $filters, $className = null, $lazy_loading = false) {

		if (is_array($filters) || $filters instanceof FilterInterface) {
			$isFilterBag = false;
			if (is_array($filters)) {
				// Is this a multiple primary key or a filter bag?
				// Let's have a look at the first item of the array to decide.
				foreach ($filters as $filter) {
					if (is_array($filter) || $filter instanceof FilterInterface) {
						$isFilterBag = true;
					}
					break;
				}
			} else {
				$isFilterBag = true;
			}
				
			if ($isFilterBag == true) {
				// If a filter bag was passer in parameter, let's perform a getObjects.
				$objects = $this->getObjects($table_name, $filters, null, null, null, $className);
				if (count($objects) == 0) {
					return null;
				} elseif (count($objects) > 1) {
					throw new DuplicateRowException("Error while querying an object for table '$table_name': ".count($objects)." rows have been returned, but we should have received at most one.");
				}
				// Return the first and only object.
				return $objects[0];
			}
		}
		$id = $filters;
		if ($this->dbConnection == null) {
			throw new TDBMException("Error while calling TdbmService->getObject(): No connection has been established on the database!");
		}
		$table_name = $this->dbConnection->toStandardcase($table_name);

		// If the ID is null, let's throw an exception
		if ($id === null) {
			throw new TDBMException("The ID you passed to TdbmService->getObject is null for the object of type '$table_name'. Objects primary keys cannot be null.");
		}

		// If the primary key is split over many columns, the IDs are passed in an array. Let's serialize this array to store it.
		if (is_array($id)) {
			$id = serialize($id);
		}

		if ($this->objectStorage->has($table_name, $id)) {
			$obj = $this->objectStorage->get($table_name, $id);
			if ($className == null || is_a($obj, $className)) {
				return $obj;
			} else {
				throw new TDBMException("Error! The object with ID '$id' for table '$table_name' has already been retrieved. The type for this object is '".get_class($obj)."'' which is not a subtype of '$className'");
			}
		}

		if ($className == null) {
			$obj = new TDBMObject($this, $table_name, $id);
		} else {
			if (!is_subclass_of($className, "Mouf\\Database\\TDBM\\TDBMObject")) {
				if (!class_exists($className)) {
					throw new TDBMException("Error while calling TDBMService->getObject: The class ".$className." does not exist.");
				} else {
					throw new TDBMException("Error while calling TDBMService->getObject: The class ".$className." should extend TDBMObject.");
				}
			}
			$obj = new $className($this, $table_name, $id);
		}

		if ($lazy_loading == false) {
			// If we are not doing lazy loading, let's load the object:
			$obj->_dbLoadIfNotLoaded();
		}

		$this->objectStorage->set($table_name, $id, $obj);

		return $obj;
	}

	/**
	 * Creates a new object that will be stored in table "table_name".
	 * If $auto_assign_id is true, the primary key of the object will be automatically be filled.
	 * Otherwise, the database system or the user will have to fill it itself (for exemple with
	 * AUTOINCREMENT in MySQL or with a sequence in POSTGRESQL).
	 * Please note that $auto_assign_id parameter is ignored if the primary key is autoincremented (MySQL only)
	 * Also, please note that $auto_assign_id does not work on tables that have primary keys on multiple
	 * columns.
	 *
	 * @param string $table_name
	 * @param boolean $auto_assign_id
	 * @param string $className Optional: The name of the class to instanciate. This class must extend the TDBMObject class. If none is specified, a TDBMObject instance will be returned.
	 * @return TDBMObject
	 */
	public function getNewObject($table_name, $auto_assign_id=true, $className = null) {
		if ($this->dbConnection == null) {
			throw new TDBMException("Error while calling TDBMObject::getNewObject(): No connection has been established on the database!");
		}
		$table_name = $this->dbConnection->toStandardcase($table_name);

		// Ok, let's verify that the table does exist:
		try {
			/*$data =*/ $this->dbConnection->getTableInfo($table_name);
		} catch (TDBMException $exception) {
			$probable_table_name = $this->dbConnection->checkTableExist($table_name);
			if ($probable_table_name == null)
			throw new TDBMException("Error while calling TDBMObject::getNewObject(): The table named '$table_name' does not exist.");
			else
			throw new TDBMException("Error while calling TDBMObject::getNewObject(): The table named '$table_name' does not exist. Maybe you meant the table '$probable_table_name'.");
		}

		if ($className == null) {
			$object = new TDBMObject($this, $table_name);
		} else {
			if (!is_string($className)) {
				throw new TDBMException("Error while calling TDBMObject::getNewObject(): The third parameter should be a string representing a class name to instantiate.");
			}
			if (!is_subclass_of($className, "Mouf\\Database\\TDBM\\TDBMObject")) {
				throw new TDBMException("Error while calling TDBMObject::getNewObject(): The class ".$className." should extend TDBMObject.");
			}
			$object = new $className($this, $table_name);
		}

		if ($auto_assign_id && !$this->isPrimaryKeyAutoIncrement($table_name)) {
			$pk_table =  $this->getPrimaryKeyStatic($table_name);
			if (count($pk_table)==1)
			{
				$root_table = $this->dbConnection->findRootSequenceTable($table_name);
				$id = $this->dbConnection->nextId($root_table);
				// If $id == 1, it is likely that the sequence was just created.
				// However, there might be already some data in the database. We will check the biggest ID in the table.
				if ($id == 1) {
					$sql = "SELECT MAX(".$this->dbConnection->escapeDBItem($pk_table[0]).") AS maxkey FROM ".$root_table;
					$res = $this->dbConnection->getAll($sql);
					// NOTE: this will work only if the ID is an integer!
					$newid = $res[0]['maxkey'] + 1;
					if ($newid>$id) {
						$id = $newid;
					}
					$this->dbConnection->setSequenceId($root_table, $id);
				}

				$object->TDBMObject_id = $id;

				$object->db_row[$pk_table[0]] = $object->TDBMObject_id;
			}
		}

		$this->_addToToSaveObjectList($object);

		return $object;
	}

	/**
	 * Removes the given object from database.
	 *
	 * @param TDBMObject $object the object to delete.
	 */
	public function deleteObject(TDBMObject $object) {
		if ($object->getTDBMObjectState() != "new" && $object->getTDBMObjectState() != "deleted")
		{
			//$primary_key = $object->getPrimaryKey();
			$pk_table = $object->getPrimaryKey();
			// Now for the object_id
			$object_id = $object->TDBMObject_id;
			// If there is only one primary key:
			if (count($pk_table)==1) {
				$sql_where = $this->dbConnection->escapeDBItem($pk_table[0])."=".$this->dbConnection->quoteSmart($object->TDBMObject_id);
			} else {
				$ids = unserialize($object_id);
				$i=0;
				$sql_where_array = array();
				foreach ($pk_table as $pk) {
					$sql_where_array[] = $this->dbConnection->escapeDBItem($pk)."=".$this->dbConnection->quoteSmart($ids[$i]);
					$i++;
				}
				$sql_where = implode(" AND ",$sql_where_array);
			}


			$sql = 'DELETE FROM '.$this->dbConnection->escapeDBItem($object->_getDbTableName()).' WHERE '.$sql_where/*.$primary_key."='".plainstring_to_dbprotected($object->TDBMObject_id)."'"*/;
			$result = $this->dbConnection->exec($sql);

			if ($result != 1)
			throw new TDBMException("Error while deleting object from table ".$object->_getDbTableName().": ".$result." have been affected.");

			$this->objectStorage->remove($object->_getDbTableName(), $object_id);
			$object->setTDBMObjectState("deleted");
		}
	}

    /**
     * This function removes the given object from the database. It will also remove all objects relied to the one given
     * by parameter before all.
     *
     * Notice: if the object has a multiple primary key, the function will not work.
     *
     * @param TDBMObject $objToDelete
     */
    public function deleteCascade(TDBMObject $objToDelete) {
        $this->deleteAllConstraintWithThisObject($objToDelete);
        $this->deleteObject($objToDelete);
    }

    /**
     * This function is used only in TDBMService (private function)
     * It will call deleteCascade function foreach object relied with a foreign key to the object given by parameter
     *
     * @param TDBMObject $obj
     * @return TDBMObjectArray
     */
    private function deleteAllConstraintWithThisObject(TDBMObject $obj) {
        $table = $obj->_getDbTableName();
        $constraints = $this->dbConnection->getConstraintsFromTable($table);
        foreach ($constraints as $constraint) {
            $sql = "SELECT DISTINCT ".$constraint["table1"].".* FROM ".$table." LEFT JOIN ".$constraint["table1"]." ON ".$table.".".$constraint["col2"]." = ".$constraint["table1"].".".$constraint["col1"]
                ." WHERE ".$table.".". $this->dbConnection->escapeDBItem($obj->getPrimaryKey()[0])."=".$this->dbConnection->quoteSmart($obj->TDBMObject_id);
            $result = $this->getObjectsFromSQL($constraint["table1"], $sql);
            foreach ($result as $tdbmObj) {
                $this->deleteCascade($tdbmObj);
            }
        }
    }

	/**
	 * The getObjectsFromSQL is used to retrieve objects from the database using a full SQL query.
	 * The TDBM library is designed to make the SQL query instead of you.
	 * So in 80% of the cases, you should use the getObjects method, which does the work for you.
	 * The getObjectsFromSQL method should be used in those 20% cases where getObjects cannot be used.
	 * Please refer to the section "What you cannot do with TDBM" of the manual for more information.
	 *
	 * The getObjectsFromSQL method is passed the kind of objects you want to retrieve, the SQL of the query,
	 * and it returns a TDBMObjectArray which is basically an array of TDBMObjects.
	 *
	 * Note that a TDBMObject always map a row in a database. Therefore, your SQL query should return all the columns
	 * of the mapped table, and only those columns. A simple way of doing this is to use the "table.*" notation.
	 *
	 * For instance, is you have a "users" table with a "boss_id" column referencing your "user_id" primary key
	 * (to handle the hierarchy in an organization), and if you want to retrieve the employees that directly work
	 * for "John Doe", you would write:
	 * $users = getObjectsBySQL("SELECT u1.* FROM users u1 JOIN users u2 ON u1.boss_id = u2.user_id WHERE u2.user_name='John Doe'");
	 *
	 * Finally, you can specify the offset and the maximum number of objects returned to you using
	 * the from and the limit parameters.
	 *
	 * @param string $table_name The kind of objects that will be returned
	 * @param string $sql The SQL of the query
	 * @param integer $from The offset
	 * @param integer $limit The maximum number of objects returned
	 * @param string $className Optional: The name of the class to instanciate. This class must extend the TDBMObject class. If none is specified, a TDBMObject instance will be returned.
	 * @return array|Generator|TDBMObjectArray The result set of the query as a TDBMObjectArray (an array of TDBMObjects with special properties)
	 */
	public function getObjectsFromSQL($table_name, $sql, $from=null, $limit=null, $className=null) {
		if ($this->dbConnection == null) {
			throw new TDBMException("Error while calling TDBMObject::getObject(): No connection has been established on the database!");
		}

		$table_name = $this->dbConnection->toStandardcase($table_name);

		$this->getPrimaryKeyStatic($table_name);

		$result = $this->dbConnection->query($sql, $from, $limit);
		
		if ($this->mode == self::MODE_COMPATIBLE_ARRAY || $this->mode == self::MODE_ARRAY) {
			if ($this->mode == self::MODE_COMPATIBLE_ARRAY) {
				$returned_objects = new TDBMObjectArray();
			} else {
				$returned_objects = [];
			}
			$keysStandardCased = array();
			$firstLine = true;
			while ($fullCaseRow = $result->fetch(\PDO::FETCH_ASSOC))
			{
				$row = array();
				if ($firstLine) {
					// $keysStandardCased is an optimization to avoid calling toStandardCaseColumn on every cell of every row.
					foreach ($fullCaseRow as $key=>$value) {
						$keysStandardCased[$key] = $this->dbConnection->toStandardCaseColumn($key);
					}
					$firstLine = false;
				}
				foreach ($fullCaseRow as $key=>$value) {
					$row[$keysStandardCased[$key]]=$value;
				}
				$pk_table = $this->primary_keys[$table_name];
				if (count($pk_table)==1)
				{
					if (!isset($keysStandardCased[$pk_table[0]])) {
						throw new TDBMException("Bad SQL request passed to getObjectsFromSQL. The SQL request should return all the rows from the '$table_name' table. Could not find primary key in this set of rows. SQL request passed: ".$sql);
					}
					$id = $row[$keysStandardCased[$pk_table[0]]];
				}
				else
				{
					// Let's generate the serialized primary key from the columns!
					$ids = array();
					foreach ($pk_table as $pk) {
						$ids[] = $row[$keysStandardCased[$pk]];
					}
					$id = serialize($ids);
				}
				if (!isset($this->objects[$table_name][$id]))
				{
					if ($className == null) {
						$obj = new TDBMObject($this, $table_name, $id);
					} elseif (is_string($className)) {
						if (!is_subclass_of($className, "Mouf\\Database\\TDBM\\TDBMObject")) {
							throw new TDBMException("Error while calling TDBM: The class ".$className." should extend TDBMObject.");
						}
						$obj = new $className($this, $table_name, $id);
					} else {
						throw new TDBMException("Error while casting TDBMObject to class, the parameter passed is not a string. Value passed: ".$className);
					}
					$this->objects[$table_name][$id] = $obj;
					$this->objects[$table_name][$id]->loadFromRow($row);
				} elseif ($this->objects[$table_name][$id]->_getStatus() == "not loaded") {
					$this->objects[$table_name][$id]->loadFromRow($row);
					// Check that the object fetched from cache is from the requested class.
					if ($className != null) {
						if (!is_subclass_of(get_class($this->objects[$table_name][$id]), $className) && get_class($this->objects[$table_name][$id]) != $className) {
							throw new TDBMException("Error while calling TDBM: An object fetched from database is already present in TDBM cache and they do not share the same class. You requested the object to be of the class ".$className." but the object available locally is of the class ".get_class($this->objects[$table_name][$id]).".");
						}
					}
				} else {
					// Check that the object fetched from cache is from the requested class.
					if ($className != null) {
						$className = ltrim($className, '\\');
						if (!is_subclass_of(get_class($this->objects[$table_name][$id]), $className) && get_class($this->objects[$table_name][$id]) != $className) {
							throw new TDBMException("Error while calling TDBM: An object fetched from database is already present in TDBM cache and they do not share the same class. You requested the object to be of the class ".$className." but the object available locally is of the class ".get_class($this->objects[$table_name][$id]).".");
						}
					}
				}
				$returned_objects[] = $this->objects[$table_name][$id];
			}
			$result->closeCursor();
			$result = null;
			return $returned_objects;
		} elseif ($this->mode == self::MODE_CURSOR) {
			return $this->getObjectsFromSQLGenerator($result, $table_name, $className, $sql);
		} else {
			throw new TDBMException("Unknown mode: ".$this->mode);
		}
	}
	
	/**
	 * Returns a generator for the database.
	 * @param unknown $result
	 * @param unknown $table_name
	 * @param unknown $className
	 * @param unknown $sql
	 */
	private function getObjectsFromSQLGenerator($result, $table_name, $className, $sql) {
		$keysStandardCased = array();
		$firstLine = true;
		while ($fullCaseRow = $result->fetch(\PDO::FETCH_ASSOC))
		{
			$row = array();
			if ($firstLine) {
				// $keysStandardCased is an optimization to avoid calling toStandardCaseColumn on every cell of every row.
				foreach ($fullCaseRow as $key=>$value) {
					$keysStandardCased[$key] = $this->dbConnection->toStandardCaseColumn($key);
				}
				$firstLine = false;
			}
			foreach ($fullCaseRow as $key=>$value) {
				$row[$keysStandardCased[$key]]=$value;
			}
			$pk_table = $this->primary_keys[$table_name];
			if (count($pk_table)==1)
			{
				if (!isset($keysStandardCased[$pk_table[0]])) {
					throw new TDBMException("Bad SQL request passed to getObjectsFromSQL. The SQL request should return all the rows from the '$table_name' table. Could not find primary key in this set of rows. SQL request passed: ".$sql);
				}
				$id = $row[$keysStandardCased[$pk_table[0]]];
			}
			else
			{
				// Let's generate the serialized primary key from the columns!
				$ids = array();
				foreach ($pk_table as $pk) {
					$ids[] = $row[$keysStandardCased[$pk]];
				}
				$id = serialize($ids);
			}
			if (!isset($this->objects[$table_name][$id]))
			{
				if ($className == null) {
					$obj = new TDBMObject($this, $table_name, $id);
				} elseif (is_string($className)) {
					if (!is_subclass_of($className, "Mouf\\Database\\TDBM\\TDBMObject")) {
						throw new TDBMException("Error while calling TDBM: The class ".$className." should extend TDBMObject.");
					}
					$obj = new $className($this, $table_name, $id);
				} else {
					throw new TDBMException("Error while casting TDBMObject to class, the parameter passed is not a string. Value passed: ".$className);
				}
				$this->objects[$table_name][$id] = $obj;
				$this->objects[$table_name][$id]->loadFromRow($row);
			} elseif ($this->objects[$table_name][$id]->_getStatus() == "not loaded") {
				$this->objects[$table_name][$id]->loadFromRow($row);
				// Check that the object fetched from cache is from the requested class.
				if ($className != null) {
					if (!is_subclass_of(get_class($this->objects[$table_name][$id]), $className) && get_class($this->objects[$table_name][$id]) != $className) {
						throw new TDBMException("Error while calling TDBM: An object fetched from database is already present in TDBM cache and they do not share the same class. You requested the object to be of the class ".$className." but the object available locally is of the class ".get_class($this->objects[$table_name][$id]).".");
					}
				}
			} else {
				// Check that the object fetched from cache is from the requested class.
				if ($className != null) {
					$className = ltrim($className, '\\');
					if (!is_subclass_of(get_class($this->objects[$table_name][$id]), $className) && get_class($this->objects[$table_name][$id]) != $className) {
						throw new TDBMException("Error while calling TDBM: An object fetched from database is already present in TDBM cache and they do not share the same class. You requested the object to be of the class ".$className." but the object available locally is of the class ".get_class($this->objects[$table_name][$id]).".");
					}
				}
			}
			yield $this->objects[$table_name][$id];
		}
		$result->closeCursor();
		$result = null;
	}

	/**
	 * This function performs a save() of all the objects that have been modified.
	 * This function is automatically called at the end of your script, so you don't have to call it yourself.
	 *
	 * Note: if you want to catch or display efficiently any error that might happen, you might want to call this
	 * method explicitly and to catch any TDBMException that it might throw like this:
	 *
	 * try {
	 * 		TDBMObject::completeSave();
	 * } catch (TDBMException e) {
	 * 		// Do something here.
	 * }
	 *
	 */
	function completeSave() {

		if (is_array($this->tosave_objects))
		{
			foreach ($this->tosave_objects as $object)
			{
				if (!$object->db_onerror && $object->db_autosave)
				{
					$object->save();
				}
			}
		}

	}

	/**
	 * This function performs a save() of all the objects that have been modified just before the program exits.
	 * It should never be called by the user, the program will call it directly.
	 *
	 */
	public function completeSaveOnExit() {
		$this->is_program_exiting = true;
		$this->completeSave();

		// Now, let's commit or rollback if needed.
		if ($this->dbConnection != null && $this->dbConnection->hasActiveTransaction()) {
			if ($this->commitOnQuit) {
				try  {
					$this->dbConnection->commit();
				} catch (Exception $e) {
					echo $e->getMessage()."<br/>";
					echo $e->getTraceAsString();
				}
			} else {
				try  {
					$this->dbConnection->rollback();
				} catch (Exception $e) {
					echo $e->getMessage()."<br/>";
					echo $e->getTraceAsString();
				}
			}
		}
	}

	/**
	 * Function used internally by TDBM.
	 * Returns true if the program is exiting.
	 *
	 * @return bool
	 */
	public function isProgramExiting() {
		return $this->is_program_exiting;
	}

	/**
	 * This function performs a save() of all the objects that have been modified, then it sets all the data to a not loaded state.
	 * Therefore, the database will be queried again next time we access the object. Meanwhile, if another process modifies the database,
	 * the changes will be retrieved when we access the object again.
	 *
	 */
	function completeSaveAndFlush() {
		$this->completeSave();

		$this->objectStorage->apply(function(TDBMObject $object) {
			/* @var $object TDBMObject */
			if (!$object->db_onerror && $object->getTDBMObjectState() == "loaded")
			{
				$object->setTDBMObjectState("not loaded");
			}
		});
	}


	/**
	 * Returns transient objects.
	 * getTransientObjectsFromSQL executes the SQL request passed, and returns a set of objects matching this request.
	 * The objects returned will not be saved in database if they are modified.
	 *
	 * This method is particularly useful for retrieving aggregated data for instance (requests with GROUP BY).
	 *
	 * For instance you can use getTransientObjectsFromSQL to rertrieve the number of users in each country:
	 *
	 * $objects = getTransientObjectsFromSQL("SELECT country_code, count(user_id) AS cnt FROM users GROUP BY country_code");
	 * foreach ($objects as $object) {
	 * 		echo "Country $object->country_code has $object->cnt users";
	 * }
	 *
	 * Note that using getObjectsFromSQL for such requests would be a mistake since getObjectsFromSQL is retrieving objects
	 * that can be saved later.
	 *
	 * TODO: make the result a TDBMObjectArray instead of an array.
	 *
	 * @param string $sql
	 * @return array the result of your query
	 */
	public function getTransientObjectsFromSQL($sql,$classname=null) {
		if ($this->dbConnection == null) {
			throw new TDBMException("Error while calling TDBMObject::getObject(): No connection has been established on the database!");
		}
		return $this->dbConnection->getAll($sql, \PDO::FETCH_CLASS,$classname);
	}


	private function to_explain_string($path) {
		$msg = '';
		foreach ($path as $constraint) {
			if ($constraint['type']=='1*') {
				$msg .= 'Table "'.$constraint['table1'].'" points to "'.$constraint['table2'].'" through its foreign key "'.$constraint['col1'].'"\n';
			}
			elseif ($constraint['type']=='*1') {
				$msg .= 'Table "'.$constraint['table1'].'" is pointed by "'.$constraint['table2'].'" through its foreign key "'.$constraint['col2'].'"\n';
			}
		}
		return $msg;
	}


	/**
	 * Returns an array of paths going from "$table" to the tables passed in the array "$tables"
	 *
	 * @param string $table The base table
	 * @param array $tables The destination tables
	 * @return unknown
	 */
	private function static_find_paths($table, $tables) {
		$this->loadCache();

		$path = array();
		$queue = array(array($table,array()));

		//$found_paths=array();
		$found = false;
		$found_depth = 0;

		$tables_paths = array();
		$cached_tables_paths = array();

		// Let's fill the $tables_paths that will contain the name of the tables needed (and the paths soon).
		// Also, let's use this moment to check if the tables we are looking for are not in cache.
		foreach ($tables as $tablename) {
			$cached_path = $this->getPathFromCache($table, $tablename);
			if ($cached_path === null) {
				$tables_paths[]['name'] = $tablename;
			} else {
				$cached_path_array = array();
				$cached_path_array['name'] = $tablename;
				$cached_path_array['founddepth'] = count($cached_path);
				$cached_path_array['paths'][] = $cached_path;
				$cached_tables_paths[] = $cached_path_array;
			}
		}

		if (count($tables_paths)>0) {

			// Let's get the maximum execution time and let's take 90% of it:
			$max_execution_time = ini_get("max_execution_time")*0.9;

			while (!empty($queue))
			{
				$ret = $this->find_paths_iter($tables_paths, $path, $queue);
				if ($found && $found_depth != count($path))
				{
					break;
				}
				if ($ret==true)
				{

					// Ok, we got one, we will continue a bit more until we reach the next level in the tree,
					// just to see if there is no ambiguity
					//$found_paths[] = $path;
					$found = true;
					$found_depth = count($path);
				}

				// At each iteration, let's check the time.
				if ($this->trackExecutionTime && microtime(true)-self::$script_start_up_time > $max_execution_time && $max_execution_time!=0) {
					// Call check table names
					$this->checkTablesExist($tables);

					// If no excecution thrown we still have a risk to run out of time.
					throw new TDBMException("Your request is too slow. 90% of the total amount of execution time allocated to this page has passed. Try to allocate more time for the execution of PHP pages by changing the max_execution_time parameter in php.ini");

				}
			}
		}

		$ambiguity =false;
		$msg = '';
		foreach ($tables_paths as $table_path) {
			// If any table has not been found, throw an exception
			if (!isset($table_path['founddepth']) || $table_path['founddepth']==null) {
				// First, check if the tables do exist.
				$this->checkTablesExist(array($table, $table_path['name']));
				// Else, throw an error.
				throw new TDBMException("Unable to find a path between table ".$table." and table ".$table_path['name'].".\nIt is likely that a constraint is missing.");
			}
			// If any table has more than 1 way to be reached, throw an exception.
			if (count($table_path['paths'])>1) {
				// If this is the first ambiguity
				if (!$ambiguity)
				$msg .= 'An ambiguity has been found during the search. Please catch this exception and execute the $exception->explainAmbiguity() to get a nice graphical view of what you should do to solve this ambiguity.';

				$msg .= "The table \"".$table_path['name']."\" can be reached using several different ways from the table \"$table\".\n\n";
				$count = 0;
				foreach ($table_path['paths'] as $path) {
					$count++;
					$msg .= "Solution $count:\n";
					$msg .= $this->to_explain_string($path)."\n\n";
				}

				$ambiguity = true;

				//throw new TDBMException($msg);
				//throw new AmbiguityException($msg, $tables_paths);
			}

			if (!$ambiguity) {
				$this->cache['paths'][$table][$table_path['name']] = $table_path['paths'][0];
				$this->saveCache();
			}
		}

		$tables_paths = array_merge($tables_paths, $cached_tables_paths);

		if ($ambiguity) {
			throw new AmbiguityException($msg, $tables_paths, $this);
		}

		//var_dump($tables_paths);
		return $tables_paths;

	}

	/**
	 * Get the path between 2 tables from the local cache
	 * 
	 * @param string $table1
	 * @param string $table2
	 * @return array|null
	 */
	private function getPathFromCache($table1, $table2) {
		if (isset($this->cache['paths'][$table1][$table2]))
		{
			return $this->cache['paths'][$table1][$table2];
		}
		elseif (isset($this->cache['paths'][$table2][$table1]))
		{
			// Let's revert the path!
			$toRevertPath = $this->cache['paths'][$table2][$table1];
			$invertedDependencies = array_map(function($depArr) {
				return array(
						'table1' => $depArr['table2'],
						'table2' => $depArr['table1'],
						'col1' => $depArr['col2'],
						'col2' => $depArr['col1'],
						'type' => (($depArr['type'] == '1*')?'*1':'1*')
				);
			}, $toRevertPath);
			return array_reverse($invertedDependencies);
		}
		return null;
	}
	
	/**
	 * This function takes an array of paths in parameter and flatten the paths into only one
	 * path while eliminating doublons.
	 * A-B/B-C
	 * and			=>	A-B/B-C/B-D
	 * A-B/B-D
	 *
	 * @param unknown_type $paths
	 */
	private function flatten_paths($paths) {
		$flat_path=array();
		foreach ($paths as $path_bigarray) {
			$path = $path_bigarray['paths'][0];

			foreach ($path as $path_step) {
				$found = false;
				foreach ($flat_path as $path_step_verify) {
					if ($path_step == $path_step_verify ||
					($path_step['table1'] == $path_step_verify['table2'] &&
					$path_step['table2'] == $path_step_verify['table1'] &&
					$path_step['col1'] == $path_step_verify['col2'] &&
					$path_step['col2'] == $path_step_verify['col1']
					)) {
						$found = true;
						break;
					}
				}
				if (!$found)
				$flat_path[] = $path_step;
			}
		}
		return $flat_path;
	}

	/**
	 * Iterative function used by static_find_paths.
	 *
	 * @param unknown_type $target_tables
	 * @param unknown_type $path
	 * @param unknown_type $queue
	 * @return unknown
	 */
	private function find_paths_iter(&$target_tables, &$path, &$queue) {
		// Get table to look at:
		$current_vars = array_shift($queue);
		$current_table = $current_vars[0];
		$path = $current_vars[1];

		//echo '-'.$current_table.'-';
		//echo '.';
		foreach ($target_tables as $id=>$target_table) {
			if ($target_table['name'] == $current_table && (!isset($target_table['founddepth']) || $target_table['founddepth']==null || $target_table['founddepth']==count($path))) {
				// When a path is found to a table, we mark the table as found with its depth.
				$target_tables[$id]['founddepth']=count($path);

				// Then we add the path to table to the target_tables array
				$target_tables[$id]['paths'][] = $path;
				//echo "found: ".$target_table;
				// If all tables have been found, return true!
				$found = true;
				foreach ($target_tables as $test_table) {
					if (!isset($test_table['founddepth']) || $test_table['founddepth'] == null) {
						$found = false;
					}
				}

				if ($found)
				return true;
			}

		}

		/*if ($target_table == $current_table) {
			return true;
		}*/

		// Let's start with 1*
		$constraints = $this->dbConnection->getConstraintsFromTable($current_table);

		foreach ($constraints as $constraint) {

			$table1 = $constraint['table1'];
			$col1 = $constraint['col1'];
			$col2 = $constraint['col2'];

			/*if ($visited[$table1][$col1]==true)
				continue;
			else
			$visited[$table1][$col1]=true;*/
			// Go through the path to see if we ever have gone through this link
			$already_done = false;
			foreach ($path as $previous_constraint)
			{
				if ($previous_constraint['type']=='1*' && $current_table == $previous_constraint["table2"] && $col2 == $previous_constraint["col2"] && $table1 == $previous_constraint["table1"] && $col1 == $previous_constraint["col1"])
				{
					//echo "YOUHOU1! $current_table $col2";
					$already_done = true;
					break;
				}
				elseif ($previous_constraint['type']=='*1' && $current_table == $previous_constraint["table1"] && $col2 == $previous_constraint["col1"] && $table1 == $previous_constraint["table2"] && $col1 == $previous_constraint["col2"])
				{
					//echo "YOUHOU2! $current_table $col2";
					$already_done = true;
					break;
				}
			}
			if ($already_done)
			continue;

			$new_path = array_merge($path, array(array("table1"=>$table1,
									"col1"=>$col1,
									"table2"=>$current_table,
									"col2"=>$col2,
									"type"=>"1*")));
			array_push($queue, array($table1, $new_path));
		}

		// Let's continue with *1
		$constraints = $this->dbConnection->getConstraintsOnTable($current_table);

		foreach ($constraints as $constraint) {
			$table2 = $constraint['table2'];
			$col2 = $constraint['col2'];
			$col1 = $constraint['col1'];
			/*if ($visited[$table2][$col2]==true)
				continue;
			else
			$visited[$table2][$col2]=true;*/
			$already_done = false;
			foreach ($path as $previous_constraint)
			{
				//echo "TTTT".$table2." ".$col2."AAAA".$previous_constraint["table1"]." ".$previous_constraint["col1"]."YYYY".$previous_constraint["type"]."PPP";
				if ($previous_constraint['type']=='1*' && $table2 == $previous_constraint["table2"] && $col2 == $previous_constraint["col2"])
				{
					//echo  "YOUHOU3! $table2 $col2";
					$already_done = true;
					break;
				}
				elseif ($previous_constraint['type']=='*1' && $table2 == $previous_constraint["table1"] && $col2 == $previous_constraint["col1"])
				{
					//echo "YOUHOU4! $table2 $col2";
					$already_done = true;
					break;
				}
			}
			if ($already_done)
			continue;

			$new_path = array_merge($path, array(array("table1"=>$table2,
									"col1"=>$col2,
									"table2"=>$current_table,
									"col2"=>$col1,
									"type"=>"*1")));
			array_push($queue, array($table2, $new_path));
		}

		return false;
	}

	/**
	 * Returns an array of objects of "table_name" kind filtered from the filter bag.
	 *
	 * The getObjects method should be the most used query method in TDBM if you want to query the database for objects.
	 * (Note: if you want to query the database for an object by its primary key, use the getObject method).
	 *
	 * The getObjects method takes in parameter:
	 * 	- table_name: the kinf of TDBMObject you want to retrieve. In TDBM, a TDBMObject matches a database row, so the
	 * 			$table_name parameter should be the name of an existing table in database.
	 *  - filter_bag: The filter bag is anything that you can use to filter your request. It can be a SQL Where clause,
	 * 			a series of TDBM_Filter objects, or even TDBMObjects or TDBMObjectArrays that you will use as filters.
	 *  - order_bag: The order bag is anything that will be used to order the data that is passed back to you.
	 * 			A SQL Order by clause can be used as an order bag for instance, or a OrderByColumn object
	 * 	- from (optionnal): The offset from which the query should be performed. For instance, if $from=5, the getObjects method
	 * 			will return objects from the 6th rows.
	 * 	- limit (optionnal): The maximum number of objects to return. Used together with $from, you can implement
	 * 			paging mechanisms.
	 *  - hint_path (optionnal): EXPERTS ONLY! The path the request should use if not the most obvious one. This parameter
	 * 			should be used only if you perfectly know what you are doing.
	 *
	 * The getObjects method will return a TDBMObjectArray. A TDBMObjectArray is an array of TDBMObjects that does behave as
	 * a single TDBMObject if the array has only one member. Refer to the documentation of TDBMObjectArray and TDBMObject
	 * to learn more.
	 *
	 * More about the filter bag:
	 * A filter is anything that can change the set of objects returned by getObjects.
	 * There are many kind of filters in TDBM:
	 * A filter can be:
	 * 	- A SQL WHERE clause:
	 * 		The clause is specified without the "WHERE" keyword. For instance:
	 * 			$filter = "users.first_name LIKE 'J%'";
	 *     	is a valid filter.
	 * 	   	The only difference with SQL is that when you specify a column name, it should always be fully qualified with
	 * 		the table name: "country_name='France'" is not valid, while "countries.country_name='France'" is valid (if
	 * 		"countries" is a table and "country_name" a column in that table, sure.
	 * 		For instance,
	 * 				$french_users = TDBMObject::getObjects("users", "countries.country_name='France'");
	 * 		will return all the users that are French (based on trhe assumption that TDBM can find a way to connect the users
	 * 		table to the country table using foreign keys, see the manual for that point).
	 * 	- A TDBMObject:
	 * 		An object can be used as a filter. For instance, we could get the France object and then find any users related to
	 * 		that object using:
	 * 				$france = TDBMObject::getObjects("country", "countries.country_name='France'");
	 * 				$french_users = TDBMObject::getObjects("users", $france);
	 *  - A TDBMObjectArray can be used as a filter too.
	 * 		For instance:
	 * 				$french_groups = TDBMObject::getObjects("groups", $french_users);
	 * 		might return all the groups in which french users can be found.
	 *  - Finally, TDBM_xxxFilter instances can be used.
	 * 		TDBM provides the developer a set of TDBM_xxxFilters that can be used to model a SQL Where query.
	 * 		Using the appropriate filter object, you can model the operations =,<,<=,>,>=,IN,LIKE,AND,OR, IS NULL and NOT
	 * 		For instance:
	 * 				$french_users = TDBMObject::getObjects("users", new EqualFilter('countries','country_name','France');
	 * 		Refer to the documentation of the appropriate filters for more information.
	 *
	 * The nice thing about a filter bag is that it can be any filter, or any array of filters. In that case, filters are
	 * 'ANDed' together.
	 * So a request like this is valid:
	 * 				$france = TDBMObject::getObjects("country", "countries.country_name='France'");
	 * 				$french_administrators = TDBMObject::getObjects("users", array($france,"role.role_name='Administrators'");
	 * This requests would return the users that are both French and administrators.
	 *
	 * Finally, if filter_bag is null, the whole table is returned.
	 *
	 * More about the order bag:
	 * The order bag contains anything that can be used to order the data that is passed back to you.
	 * The order bag can contain two kinds of objects:
	 * 	- A SQL ORDER BY clause:
	 * 		The clause is specified without the "ORDER BY" keyword. For instance:
	 * 			$orderby = "users.last_name ASC, users.first_name ASC";
	 *     	is a valid order bag.
	 * 		The only difference with SQL is that when you specify a column name, it should always be fully qualified with
	 * 		the table name: "country_name ASC" is not valid, while "countries.country_name ASC" is valid (if
	 * 		"countries" is a table and "country_name" a column in that table, sure.
	 * 		For instance,
	 * 				$french_users = TDBMObject::getObjects("users", null, "countries.country_name ASC");
	 * 		will return all the users sorted by country.
	 *  - A OrderByColumn object
	 * 		This object models a single column in a database.
	 *
	 * @param string $table_name The name of the table queried
	 * @param mixed $filter_bag The filter bag (see above for complete description)
	 * @param mixed $orderby_bag The order bag (see above for complete description)
	 * @param integer $from The offset
	 * @param integer $limit The maximum number of rows returned
	 * @param string $className Optional: The name of the class to instanciate. This class must extend the TDBMObject class. If none is specified, a TDBMObject instance will be returned.
	 * @param unknown_type $hint_path Hints to get the path for the query (expert parameter, you should leave it to null).
	 * @return TDBMObjectArray A TDBMObjectArray containing the resulting objects of the query.
	 */
	public function getObjects($table_name, $filter_bag=null, $orderby_bag=null, $from=null, $limit=null, $className=null, $hint_path=null) {
		if ($this->dbConnection == null) {
			throw new TDBMException("Error while calling TDBMObject::getObject(): No connection has been established on the database!");
		}
		return $this->getObjectsByMode('getObjects', $table_name, $filter_bag, $orderby_bag, $from, $limit, $className, $hint_path);
	}

	/**
	 * Performs a request and returns only the number of records returned from the database, applying the filterbag.
	 * This function takes essentially the same parameters as the getObjects function (at least the same $filter_bag).
	 *
	 * @param unknown_type $table_name The name of the table queried
	 * @param unknown_type $filter_bag The filter bag (see getObjects for complete description)
	 * @param unknown_type $hint_path
	 * @return integer
	 */
	public function getCount($table_name, $filter_bag=null, $hint_path=null) {
		if ($this->dbConnection == null) {
			throw new TDBMException("Error while calling TDBMObject::getObject(): No connection has been established on the database!");
		}
		return $this->getObjectsByMode('getCount', $table_name, $filter_bag, null, null, null, null, $hint_path);
	}

	/**
	 * Returns the SQL that would be used by getObjects if it was called with the same parameters.
	 *
	 * @param string $table_name The name of the table queried
	 * @param unknown_type $filter_bag The filter bag (see above for complete description)
	 * @param unknown_type $orderby_bag The order bag (see above for complete description)
	 * @param integer $from The offset
	 * @param integer $limit The maximum number of rows returned
	 * @param unknown_type $hint_path Hints to get the path for the query (expert parameter, you should leave it to null).
	 * @return string The SQL that would be executed.
	 */
	public function explainSQLGetObjects($table_name, $filter_bag=null, $orderby_bag=null, $from=null, $limit=null, $hint_path=null) 	{
		if ($this->dbConnection == null) {
			throw new TDBMException("Error while calling TDBMObject::getObject(): No connection has been established on the database!");
		}
		return $this->getObjectsByMode('explainSQL', $table_name, $filter_bag, $orderby_bag, $from, $limit, $hint_path);
	}

	/**
	 * Returns the "jointure-tree" that would be used by getObjects if it was called with the same parameters as text (human readable).
	 *
	 * @param string $table_name The name of the table queried
	 * @param unknown_type $filter_bag The filter bag (see above for complete description)
	 * @param unknown_type $orderby_bag The order bag (see above for complete description)
	 * @param integer $from The offset
	 * @param integer $limit The maximum number of rows returned
	 * @param unknown_type $hint_path Hints to get the path for the query (expert parameter, you should leave it to null).
	 * @return string The SQL that would be executed.
	 */
	public function explainRequestAsTextGetObjects($table_name, $filter_bag=null, $orderby_bag=null, $from=null, $limit=null, $hint_path=null) 	{
		if ($this->dbConnection == null) {
			throw new TDBMException("Error while calling TDBMObject::getObject(): No connection has been established on the database!");
		}
		$tree = $this->getObjectsByMode('explainTree', $table_name, $filter_bag, $orderby_bag, $from, $limit, $hint_path);
		return $tree->displayText();
	}


	/**
	 * Returns the "jointure-tree" that would be used by getObjects if it was called with the same parameters as HTML.
	 * Just "echo" this text to an HTML page to get a drawing of the request performed.
	 *
	 * @param string $table_name The name of the table queried
	 * @param unknown_type $filter_bag The filter bag (see above for complete description)
	 * @param unknown_type $orderby_bag The order bag (see above for complete description)
	 * @param integer $from The offset
	 * @param integer $limit The maximum number of rows returned
	 * @param unknown_type $hint_path Hints to get the path for the query (expert parameter, you should leave it to null).
	 * @return string The SQL that would be executed.
	 */
	public function explainRequestAsHTMLGetObjects($table_name, $filter_bag=null, $orderby_bag=null, $from=null, $limit=null, $hint_path=null, $x=10, $y=10) 	{
		if ($this->dbConnection == null) {
			throw new TDBMException("Error while calling TDBMObject::getObject(): No connection has been established on the database!");
		}
		$tree = $this->getObjectsByMode('explainTree', $table_name, $filter_bag, $orderby_bag, $from, $limit, $hint_path);
		return $this->drawTree($tree,$x,$y);
	}

	/**
	 * Performs the real operations for getObjects, explainSQL and explainTree.
	 * It takes as an entry the same parameters, with an additional parameter $mode.
	 *
	 * @param string $mode One of 'getObjects', 'explainSQL', 'explainTree'
	 * @param string $table_name The name of the table queried
	 * @param unknown_type $filter_bag The filter bag (see above for complete description)
	 * @param unknown_type $orderby_bag The order bag (see above for complete description)
	 * @param integer $from The offset
	 * @param integer $limit The maximum number of rows returned
	 * @param string $className Optional: The name of the class to instanciate. This class must extend the TDBMObject class. If none is specified, a TDBMObject instance will be returned.
	 * @param unknown_type $hint_path Hints to get the path for the query (expert parameter, you should leave it to null).
	 * @return array|Generator|TDBMObjectArray An array or object containing the resulting objects of the query.
	 */
	public function getObjectsByMode($mode, $table_name, $filter_bag=null, $orderby_bag=null, $from=null, $limit=null, $className=null, $hint_path=null) {
		$this->completeSave();
		$this->loadCache();

		// Let's get the filter from the filter_bag
		$filter = $this->buildFilterFromFilterBag($filter_bag);

		// Let's get the order array from the order_bag
		$orderby_bag2 = $this->buildOrderArrayFromOrderBag($orderby_bag);

		// Now, let's find the path from the needed tables of the resulting filter.

		// Let's get needed tables from the filters
		$needed_table_array_for_filters = $filter->getUsedTables();

		$needed_table_array_for_orderby = array();
		// Let's get needed tables from the order by
		foreach ($orderby_bag2 as $orderby) {
			$needed_table_array_for_orderby = array_merge($needed_table_array_for_orderby, $orderby->getUsedTables());
		}

		// Remove the asked table from the needed table array for group bys.
		foreach ($needed_table_array_for_orderby as $id=>$needed_table_name)
		{
			if ($needed_table_name == $table_name) {
				unset($needed_table_array_for_orderby[$id]);
			}
		}

		$needed_table_array = array_flip(array_flip(array_merge($needed_table_array_for_filters, $needed_table_array_for_orderby)));

		// Remove the asked table from the needed table array.
		foreach ($needed_table_array as $id=>$needed_table_name)
		{
			if ($needed_table_name == $table_name) {
				unset($needed_table_array[$id]);
			}
		}

		if (count($needed_table_array)==0)
		{
			//$table_number = 1;
			$sql = $this->dbConnection->escapeDBItem($table_name); //Make by Pierre PIV (add escapeDBItem)

			if ($mode == 'explainTree')
			throw new TDBMException("TODO: explainTree not implemented for only one table.");
		}
		else {
			if ($hint_path!=null && $mode != 'explainTree')
			{
				$path = $hint_path;
				$flat_path = $this->flatten_paths($path);
			}
			else
			{
				$full_paths = $this->static_find_paths($table_name,$needed_table_array);

				if ($mode == 'explainTree') {
					return $this->getTablePathsTree($full_paths);
				}

				$flat_path = $this->flatten_paths($full_paths);
			}

			// Now, let's generate the SQL and let's call getObjectsBySQL.

			//print_r($flat_path);

			$constraint = $flat_path[0];

			//$table_number=1;
			$sql = $this->dbConnection->escapeDBItem($constraint['table2']);

			foreach ($flat_path as $constraint) {
				//$previous_table_number = $table_number;
				//$table_number++;
				$table1 = $constraint['table2'];
				$table2 = $constraint['table1'];
				$col2 = $constraint['col1'];
				$col1 = $constraint['col2'];
					
				$sql = "($sql LEFT JOIN ".$this->dbConnection->escapeDBItem($table2)." ON
				".$this->dbConnection->escapeDBItem($table1).".".$this->dbConnection->escapeDBItem($col1)."=".$this->dbConnection->escapeDBItem($table2).".".$this->dbConnection->escapeDBItem($col2).")";
			}
		}


		// Now, for each needed table to perform the order by, we must verify if the relationship between the order by and the object is indeed a 1* relationship
		foreach ($needed_table_array_for_orderby as $target_table_table) {
			// Get the path between the main table and the target group by table

			// TODO! Pas bon!!!! Faut le quÃ©rir, hÃ©las!
			// Mais comment gÃ©rer Ã§a sans plomber les perfs et en utilisant le path fourni?????

			$path = $this->getPathFromCache($table_name, $target_table_table);
			
			/*
			 echo 'beuuuh';
			var_dump($needed_table_array_for_orderby);
			var_dump($path);
			var_dump($target_table_table);
			*/
			/**********************************
			 * Modifier par Marc de *1 vers 1*
			* (sur les conseils de David !)
			*/
			$is_ok = true;
			foreach ($path as $step) {
				if ($step["type"]=="1*") {
					$is_ok = false;
					break;
				}
			}

			if (!$is_ok) {
				throw new TDBMException("Error in querying database from getObjectsByFilter. You tried to order your data according to a column of the '$target_table_table' table. However, the '$target_table_table' table has a many to 1 relationship with the '$table_name' table. This means that one '$table_name' object can contain many '$target_table_table' objects. Therefore, trying to order '$table_name' objects using '$target_table_table' objects is meaningless and cannot be performed.");
			}
		}

		// In a SELECT DISTINCT ... ORDER BY ... clause, the orderbyed columns must appear!
		// Therefore, we must be able to parse the Orderby columns requested, give them dummy names and remove them afterward!
		// Get the column statement and the order by statement
		$orderby_statement = '';
		$orderby_column_statement = '';

		if (count($orderby_bag2)>0) {

			// make an array of columns
			$orderby_columns_array = array();
			foreach ($orderby_bag2 as $orderby_object) {
				$orderby_columns_array = array_merge($orderby_columns_array, $orderby_object->toSqlStatementsArray());
			}

			$orderby_statement = ' ORDER BY '.implode(',',$orderby_columns_array);
			$count = 0;
			foreach ($orderby_columns_array as $id=>$orderby_statement_phrase) {
				// Let's remove the trailing ASC or DESC and add AS tdbm_reserved_col_Xxx
				$res = strripos($orderby_statement_phrase, 'ASC');
				if ($res !== false) {
					$orderby_statement_phrase = substr($orderby_statement_phrase, 0, $res);
				} else {
					$res = strripos($orderby_statement_phrase, 'DESC');
					if ($res !== false) {
						$orderby_statement_phrase = substr($orderby_statement_phrase, 0, $res);
					}
				}


				$orderby_columns_array[$id] = $orderby_statement_phrase.' AS tdbm_reserved_col_'.$count;
				$count++;
			}
			$orderby_column_statement = ', '.implode(',',$orderby_columns_array);
		}

		if ($mode=="getCount") {
			// Let's get the list of primary keys to perform a DISTINCT request.
			$pk_table = $this->getPrimaryKeyStatic($table_name);
				
			$pk_arr = array();
			foreach ($pk_table as $pk) {
				$pk_arr[] = $table_name.'.'.$pk;
			}
			$pk_str = implode(',', $pk_arr);
				
			$sql = "SELECT COUNT(DISTINCT $pk_str) FROM $sql";

			$where_clause = $filter->toSql($this->dbConnection);
			if ($where_clause != '')
			$sql .= ' WHERE '.$where_clause;

			// Now, let's perform the request:
			$result = $this->dbConnection->getOne($sql, array());

			return $result;
		}

		$sql = "SELECT DISTINCT ".$this->dbConnection->escapeDBItem($table_name).".* $orderby_column_statement FROM $sql";

		$where_clause = $filter->toSql($this->dbConnection);
		if ($where_clause != '')
		$sql .= ' WHERE '.$where_clause;

		$sql .= $orderby_statement;

			
		if ($mode == 'explainSQL') {
			return $sql;
		}
		return $this->getObjectsFromSQL($table_name, $sql,  $from, $limit, $className);

	}

	/**
	 * Takes in input a filter_bag (which can be about anything from a string to an array of TDBMObjects... see above from documentation),
	 * and gives back a proper Filter object.
	 *
	 * @param unknown_type $filter_bag
	 * @return FilterInterface
	 */
	public function buildFilterFromFilterBag($filter_bag) {
		// First filter_bag should be an array, if it is a singleton, let's put it in an array.
		if ($filter_bag === null) {
			$filter_bag = array();
		} elseif (!is_array($filter_bag)) {
			$filter_bag = array($filter_bag);
		}
		elseif (is_a($filter_bag, 'Mouf\\Database\\TDBM\\TDBMObjectArray')) {
			$filter_bag = array($filter_bag);
		}

		// Second, let's take all the objects out of the filter bag, and let's make filters from them
		$filter_bag2 = array();
		foreach ($filter_bag as $thing) {
			if (is_a($thing,'Mouf\\Database\\TDBM\\Filters\\FilterInterface')) {
				$filter_bag2[] = $thing;
			} elseif (is_a($thing,'Mouf\\Database\\TDBM\\TDBMObject')) {
				$pk_table = $thing->getPrimaryKey();
				// If there is only one primary key:
				if (count($pk_table)==1) {
					//$sql_where = "t1".$pk_table[0]."=".$this->db_connection->quoteSmart($this->TDBMObject_id);
					$filter_bag2[] = new EqualFilter($thing->_getDbTableName(), $pk_table[0], $thing->$pk_table[0]);
				} else {
					//$ids = unserialize($this->TDBMObject_id);
					//$i=0;
					$filter_bag_temp_and=array();
					foreach ($pk_table as $pk) {
						$filter_bag_temp_and[] = new EqualFilter($thing->_getDbTableName(), $pk, $thing->$pk);
					}
					$filter_bag2[] = new AndFilter($filter_bag_temp_and);
					//$sql_where = implode(" AND ",$sql_where_array);
				}
				//$primary_key = $thing->getPrimaryKey();

				//$filter_bag2[] = new EqualFilter($thing->_getDbTableName(), $primary_key, $thing->$primary_key);
			} elseif (is_string($thing)) {
				$filter_bag2[] = new SqlStringFilter($thing);
			} elseif (is_a($thing,'Mouf\\Database\\TDBM\\TDBMObjectArray') && count($thing)>0) {
				// Get table_name and column_name
				$filter_table_name = $thing[0]->_getDbTableName();
				$filter_column_names = $thing[0]->getPrimaryKey();

				// If there is only one primary key, we can use the InFilter
				if (count($filter_column_names)==1) {
					$primary_keys_array = array();
					$filter_column_name = $filter_column_names[0];
					foreach ($thing as $TDBMObject) {
						$primary_keys_array[] = $TDBMObject->$filter_column_name;
					}
					$filter_bag2[] = new InFilter($filter_table_name, $filter_column_name, $primary_keys_array);
				}
				// else, we must use a (xxx AND xxx AND xxx) OR (xxx AND xxx AND xxx) OR (xxx AND xxx AND xxx)...
				else
				{
					$filter_bag_and = array();
					foreach ($thing as $TDBMObject) {
						$filter_bag_temp_and=array();
						foreach ($filter_column_names as $pk) {
							$filter_bag_temp_and[] = new EqualFilter($TDBMObject->_getDbTableName(), $pk, $TDBMObject->$pk);
						}
						$filter_bag_and[] = new AndFilter($filter_bag_temp_and);
					}
					$filter_bag2[] = new OrFilter($filter_bag_and);
				}


			} elseif (!is_a($thing,'Mouf\\Database\\TDBM\\TDBMObjectArray') && $thing!==null) {
				throw new TDBMException("Error in filter bag in getObjectsByFilter. An object has been passed that is neither a filter, nor a TDBMObject, nor a TDBMObjectArray, nor a string, nor null.");
			}
		}

		// Third, let's take all the filters and let's apply a huge AND filter
		$filter = new AndFilter($filter_bag2);

		return $filter;
	}

	/**
	 * Takes in input an order_bag (which can be about anything from a string to an array of OrderByColumn objects... see above from documentation),
	 * and gives back an array of OrderByColumn / OrderBySQLString objects.
	 *
	 * @param unknown_type $orderby_bag
	 * @return array
	 */
	public function buildOrderArrayFromOrderBag($orderby_bag) {
		// Fourth, let's apply the same steps to the orderby_bag
		// 4-1 orderby_bag should be an array, if it is a singleton, let's put it in an array.

		if (!is_array($orderby_bag))
		$orderby_bag = array($orderby_bag);

		// 4-2, let's take all the objects out of the orderby bag, and let's make objects from them
		$orderby_bag2 = array();
		foreach ($orderby_bag as $thing) {
			if (is_a($thing,'Mouf\\Database\\TDBM\\Filters\\OrderBySQLString')) {
				$orderby_bag2[] = $thing;
			} elseif (is_a($thing,'Mouf\\Database\\TDBM\\Filters\\OrderByColumn')) {
				$orderby_bag2[] = $thing;
			} elseif (is_string($thing)) {
				$orderby_bag2[] = new OrderBySQLString($thing);
			} elseif ($thing !== null) {
				throw new TDBMException("Error in orderby bag in getObjectsByFilter. An object has been passed that is neither a OrderBySQLString, nor a OrderByColumn, nor a string, nor null.");
			}
		}
		return $orderby_bag2;
	}

	/**
	 * Takes in entry an array of table names.
	 * Throws a TDBMException if one of those table does not exist.
	 *
	 * @param unknown_type $tables
	 */
	private function checkTablesExist($tables) {
		foreach ($tables as $table) {
			$possible_tables = $this->dbConnection->checkTableExist($table);
			if ($possible_tables !== true)
			{
				if (count($possible_tables)==1)
				$str = "Could not find table '$table'. Maybe you meant this table: '".$possible_tables[0]."'";
				else
				$str = "Could not find table '$table'. Maybe you meant one of those tables: '".implode("', '",$possible_tables)."'";
				throw new TDBMException($str);
			}
		}
	}


	/**
	 * This function returns a DisplayNode tree modeling the $table_path.
	 *
	 * @param unknown_type $table_paths
	 */
	public function getTablePathsTree($table_paths) {
		//var_dump($table_paths);
		$tree = new DisplayNode($table_paths[0]['paths'][0][0]['table2']);

		/*if ($table_paths[0]['paths'][0][0]['link']=='*1')
			$tree = new DisplayNode($table_paths[0]['paths'][0][0]['table2']);
		else
		$tree = new DisplayNode($table_paths[0]['paths'][0][0]['table1']);*/

		foreach ($table_paths as $table_path) {
			$path = $table_path['paths'][0];

			// We should create the tree, and at each pass, go down as far as we can in the tree.
			// If we can't go further, we add nodes.
			$current_node = $tree;
			$found = true;
			foreach ($path as $link) {
				if ($found==true)
				{
					if (is_array($current_node->getChildren()))
					{
						foreach ($current_node->getChildren() as $child)
						{
							if ($link['table1']==$child->table_name &&
							$link['col1']==$child->keyNode &&
							$link['col2']==$child->keyParent &&
							$link['type']==$child->link_type) {
								$current_node = $child;
							}
							else
							{
								// Now, we must add the rest of the links to the tree.
								$found = false;
							}
						}
					}
					else
					$found = false;

				}

				if ($found==false)
				{
					$current_node = new DisplayNode($link['table1'], $current_node, $link['type'], $link['col2'], $link['col1']);
					/*if ($link['type']=='*1')
						$current_node = new DisplayNode($link['table1'], $current_node, $link['type'], $link['col2'], $link['col1']);
					else
					$current_node = new DisplayNode($link['table2'], $current_node, $link['type'], $link['col1'], $link['col2']);*/
				}
			}

		}

		$tree->computeWidth();

		return $tree;

	}

	/**
	 * This function returns the HTML to draw a tree of DisplayNode.
	 *
	 * @param unknown_type $tree
	 */
	public function drawTree($tree, $x, $y, &$ret_width=0, &$ret_height=0) {

		// Let's get the background div:
		$treeDepth = $tree->computeDepth(1)-1;
		$treeWidth = $tree->width;

		$ret_width = ($treeWidth*(DisplayNode::$box_width+DisplayNode::$interspace_width)+DisplayNode::$border*4-DisplayNode::$interspace_width);
		$ret_height = ($treeDepth*(DisplayNode::$box_height+DisplayNode::$interspace_height)+DisplayNode::$border*4-DisplayNode::$interspace_height);

		$str = "<div style='position:absolute; left:".($x+DisplayNode::$left_start-DisplayNode::$border)."px; top:".($y+DisplayNode::$top_start-DisplayNode::$border)."px; width:".$ret_width."px; height:".$ret_height."; background-color:#EEEEEE; color: white; text-align:center;'></div>";

		$str .= $tree->draw(0,0, $x, $y);

		return $str;

	}

	/**
	 * Checks if there is a autoincrement mecanism on the primary key for the table passed in parameter.
	 * 
	 * @param string $table
	 */
	private function isPrimaryKeyAutoIncrement($table) {
		$cols = $this->dbConnection->getPrimaryKey($table);
		if (count($cols) != 1) {
			return false;
		}
		return $cols[0]->autoIncrement;
	}
	
	public function getPrimaryKeyStatic($table) {
		if (!isset($this->primary_keys[$table]))
		{
			$arr = array();
			foreach ($this->dbConnection->getPrimaryKey($table) as $col) {
				$arr[] = $col->name;
			}
			// The primary_keys contains only the column's name, not the DB_Column object.
			$this->primary_keys[$table] = $arr;
			if (empty($this->primary_keys[$table]))
			{
				// Unable to find primary key.... this is an error
				// Let's try to be precise in error reporting. Let's try to find the table.
				$tables = $this->dbConnection->checkTableExist($table);
				if ($tables === true)
				throw new TDBMException("Could not find table primary key for table '$table'. Please define a primary key for this table.");
				elseif ($tables !== null) {
					if (count($tables)==1)
					$str = "Could not find table '$table'. Maybe you meant this table: '".$tables[0]."'";
					else
					$str = "Could not find table '$table'. Maybe you meant one of those tables: '".implode("', '",$tables)."'";
					throw new TDBMException($str);
				}
			}
		}
		return $this->primary_keys[$table];
	}

	/**
	 * This is an internal function, you should not use it in your application.
	 * This is used internally by TDBM to add an object to the object cache.
	 *
	 * @param TDBMObject $object
	 */
	public function _addToCache(TDBMObject $object) {
		$this->objectStorage->set($object->_getDbTableName(), $object->TDBMObject_id, $object);
	}

	/**
	 * This is an internal function, you should not use it in your application.
	 * This is used internally by TDBM to remove the object from the list of objects that have been
	 * created/updated but not saved yet.
	 *
	 * @param TDBMObject $myObject
	 */
	public function _removeFromToSaveObjectList(TDBMObject $myObject) {
		foreach ($this->tosave_objects as $id=>$object) {
			if ($object == $myObject)
			{
				unset($this->tosave_objects[$id]);
				break;
			}
		}
	}

	/**
	 * This is an internal function, you should not use it in your application.
	 * This is used internally by TDBM to add an object to the list of objects that have been
	 * created/updated but not saved yet.
	 *
	 * @param TDBMObject $myObject
	 */
	public function _addToToSaveObjectList(TDBMObject $myObject) {
		$this->tosave_objects[] = $myObject;
	}

	/**
	 * Generates all the daos and beans.
	 *
	 * @param string $daoFactoryClassName The classe name of the DAO factory
	 * @param string $sourcedirectory The source directory for the files (root of the PSR-0), relative to ROOT_PATH, with no trailing or ending /
	 * @param string $daonamespace The namespace for the DAOs, without trailing \
	 * @param string $beannamespace The Namespace for the beans, without trailing \
	 * @param bool $support If the generated daos should keep support for old functions (eg : getUserList and getList)
	 * @param bool $storeInUtc If the generated daos should store the date in UTC timezone instead of user's timezone.
	 * @return string[] the list of tables
	 */
	public function generateAllDaosAndBeans($daoFactoryClassName, $sourcedirectory, $daonamespace, $beannamespace, $support, $storeInUtc) {
		$tdbmDaoGenerator = new TDBMDaoGenerator($this->dbConnection);
		return $tdbmDaoGenerator->generateAllDaosAndBeans($daoFactoryClassName, $sourcedirectory, $daonamespace, $beannamespace, $support, $storeInUtc);
	}
}

TDBMService::$script_start_up_time = microtime(true);
