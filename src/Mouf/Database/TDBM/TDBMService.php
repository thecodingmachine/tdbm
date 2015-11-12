<?php
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

namespace Mouf\Database\TDBM;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Mouf\Database\MagicQuery;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use Mouf\Database\TDBM\Filters\OrderBySQLString;
use Mouf\Database\TDBM\Utils\TDBMDaoGenerator;
use Mouf\Utils\Cache\CacheInterface;
use SQLParser\Node\ColRef;

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
	
	/**
	 * The database connection.
	 *
	 * @var Connection
	 */
	private $connection;

	/**
	 * The cache service to cache data.
	 *
	 * @var CacheInterface
	 */
	private $cacheService;

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
	 * The default autosave mode for the objects
	 * True to automatically save the object.
	 * If false, the user must explicitly call the save() method to save the object.
	 *
	 * @var boolean
	 */
	//private $autosave_default = false;

	/**
	 * Cache of table of primary keys.
	 * Primary keys are stored by tables, as an array of column.
	 * For instance $primary_key['my_table'][0] will return the first column of the primary key of table 'my_table'.
	 *
	 * @var string[]
	 */
	private $primaryKeysColumns;

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

	/**
	 * Table of new objects not yet inserted in database or objects modified that must be saved.
	 * @var DbRow[]
	 */
	private $toSaveObjects = array();

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

	/**
	 * Map associating a table name to a fully qualified Bean class name
	 * @var array
	 */
	private $tableToBeanMap = [];

	/**
	 * @var \ReflectionClass[]
	 */
	private $reflectionClassCache;

	/**
	 * @param Connection $connection The DBAL DB connection to use
	 * @param Cache|null $cache A cache service to be used
	 * @param SchemaAnalyzer $schemaAnalyzer The schema analyzer that will be used to find shortest paths...
	 * 										 Will be automatically created if not passed.
	 */
	public function __construct(Connection $connection, Cache $cache = null, SchemaAnalyzer $schemaAnalyzer = null) {
		//register_shutdown_function(array($this,"completeSaveOnExit"));
		if (extension_loaded('weakref')) {
			$this->objectStorage = new WeakrefObjectStorage();
		} else {
			$this->objectStorage = new StandardObjectStorage();
		}
		$this->connection = $connection;
		if ($cache !== null) {
			$this->cache = $cache;
		} else {
			$this->cache = new VoidCache();
		}
		if ($schemaAnalyzer) {
			$this->schemaAnalyzer = $schemaAnalyzer;
		} else {
			$this->schemaAnalyzer = new SchemaAnalyzer($this->connection->getSchemaManager(), $this->cache, $this->getConnectionUniqueId());
		}

		$this->magicQuery = new MagicQuery($this->connection, $this->cache, $this->schemaAnalyzer);

		$this->tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($connection, $this->cache, $this->schemaAnalyzer);
		$this->cachePrefix = $this->tdbmSchemaAnalyzer->getCachePrefix();

		if (self::$script_start_up_time === null) {
			self::$script_start_up_time = microtime(true);
		}

	}


	/**
	 * Returns the object used to connect to the database.
	 *
	 * @return Connection
	 */
	public function getConnection() {
		return $this->connection;
	}

	/**
	 * Creates a unique cache key for the current connection.
	 * @return string
	 */
	private function getConnectionUniqueId() {
		return hash('md4', $this->connection->getHost()."-".$this->connection->getPort()."-".$this->connection->getDatabase()."-".$this->connection->getDriver()->getName());
	}

	/**
	 * Returns true if the objects will save automatically by default,
	 * false if an explicit call to save() is required.
	 *
	 * The behaviour can be overloaded by setAutoSaveMode on each object.
	 *
	 * @return boolean
	 */
	/*public function getDefaultAutoSaveMode() {
		return $this->autosave_default;
	}*/

	/**
	 * Sets the autosave mode:
	 * true if the object will save automatically,
	 * false if an explicit call to save() is required.
	 *
	 * @Compulsory
	 * @param boolean $autoSave
	 */
	/*public function setDefaultAutoSaveMode($autoSave = true) {
		$this->autosave_default = $autoSave;
	}*/

	/**
	 * Sets the fetch mode of the result sets returned by `getObjects`.
	 * Can be one of: TDBMObjectArray::MODE_CURSOR or TDBMObjectArray::MODE_ARRAY or TDBMObjectArray::MODE_COMPATIBLE_ARRAY
	 *
	 * In 'MODE_ARRAY' mode (default), the result is a ResultIterator object that behaves like an array. Use this mode by default (unless the list returned is very big).
	 * In 'MODE_CURSOR' mode, the result is a ResultIterator object. If you scan it many times (by calling several time a foreach loop), the query will be run
	 * several times. In cursor mode, you cannot access the result set by key. Use this mode for large datasets processed by batch.
	 *
	 * @param int $mode
	 */
	public function setFetchMode($mode) {
		if ($mode !== self::MODE_CURSOR && $mode !== self::MODE_ARRAY) {
			throw new TDBMException("Unknown fetch mode: '".$this->mode."'");
		}
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
	/*private function loadCache() {
		// TODO: evaluate for 4.0
		if ($this->cache == null) {
			if ($this->cacheService == null) {
				throw new TDBMException("A cache service must be explicitly bound to the TDBM Service. Please configure your instance of TDBM Service.");
			}
			$this->cache = $this->cacheService->get($this->cacheKey);
		}
	}*/

	/**
	 * Saves the cache.
	 *
	 */
	private function saveCache() {
		// TODO: evaluate for 4.0
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
/*	public function getObject($table_name, $filters, $className = null, $lazy_loading = false) {

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
				if ($objects instanceof \Generator) {
					return $objects->current();
				} else {
					return $objects[0];
				}
			}
		}
		$id = $filters;
		if ($this->connection == null) {
			throw new TDBMException("Error while calling TdbmService->getObject(): No connection has been established on the database!");
		}
		$table_name = $this->connection->toStandardcase($table_name);

		// If the ID is null, let's throw an exception
		if ($id === null) {
			throw new TDBMException("The ID you passed to TdbmService->getObject is null for the object of type '$table_name'. Objects primary keys cannot be null.");
		}

		// If the primary key is split over many columns, the IDs are passed in an array. Let's serialize this array to store it.
		if (is_array($id)) {
			$id = serialize($id);
		}

		if ($className === null) {
			if (isset($this->tableToBeanMap[$table_name])) {
				$className = $this->tableToBeanMap[$table_name];
			} else {
				$className = "Mouf\\Database\\TDBM\\TDBMObject";
			}
		}

		if ($this->objectStorage->has($table_name, $id)) {
			$obj = $this->objectStorage->get($table_name, $id);
			if (is_a($obj, $className)) {
				return $obj;
			} else {
				throw new TDBMException("Error! The object with ID '$id' for table '$table_name' has already been retrieved. The type for this object is '".get_class($obj)."'' which is not a subtype of '$className'");
			}
		}

		if ($className != "Mouf\\Database\\TDBM\\TDBMObject" && !is_subclass_of($className, "Mouf\\Database\\TDBM\\TDBMObject")) {
			if (!class_exists($className)) {
				throw new TDBMException("Error while calling TDBMService->getObject: The class ".$className." does not exist.");
			} else {
				throw new TDBMException("Error while calling TDBMService->getObject: The class ".$className." should extend TDBMObject.");
			}
		}
		$obj = new $className($this, $table_name, $id);

		if ($lazy_loading == false) {
			// If we are not doing lazy loading, let's load the object:
			$obj->_dbLoadIfNotLoaded();
		}

		$this->objectStorage->set($table_name, $id, $obj);

		return $obj;
	}*/

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
		if ($this->connection == null) {
			throw new TDBMException("Error while calling TDBMObject::getNewObject(): No connection has been established on the database!");
		}
		$table_name = $this->connection->toStandardcase($table_name);

		// Ok, let's verify that the table does exist:
		try {
			/*$data =*/ $this->connection->getTableInfo($table_name);
		} catch (TDBMException $exception) {
			$probable_table_name = $this->connection->checkTableExist($table_name);
			if ($probable_table_name == null)
			throw new TDBMException("Error while calling TDBMObject::getNewObject(): The table named '$table_name' does not exist.");
			else
			throw new TDBMException("Error while calling TDBMObject::getNewObject(): The table named '$table_name' does not exist. Maybe you meant the table '$probable_table_name'.");
		}

		if ($className === null) {
			if (isset($this->tableToBeanMap[$table_name])) {
				$className = $this->tableToBeanMap[$table_name];
			} else {
				$className = "Mouf\\Database\\TDBM\\TDBMObject";
			}
		}

		if (!is_string($className)) {
			throw new TDBMException("Error while calling TDBMObject::getNewObject(): The third parameter should be a string representing a class name to instantiate.");
		}
		if (!is_a($className, "Mouf\\Database\\TDBM\\TDBMObject", true)) {
			throw new TDBMException("Error while calling TDBMObject::getNewObject(): The class ".$className." should extend TDBMObject.");
		}
		$object = new $className($this, $table_name);

		if ($auto_assign_id && !$this->isPrimaryKeyAutoIncrement($table_name)) {
			$pk_table =  $this->getPrimaryKeyColumns($table_name);
			if (count($pk_table)==1)
			{
				$root_table = $this->connection->findRootSequenceTable($table_name);
				$id = $this->connection->nextId($root_table);
				// If $id == 1, it is likely that the sequence was just created.
				// However, there might be already some data in the database. We will check the biggest ID in the table.
				if ($id == 1) {
					$sql = "SELECT MAX(".$this->connection->escapeDBItem($pk_table[0]).") AS maxkey FROM ".$root_table;
					$res = $this->connection->getAll($sql);
					// NOTE: this will work only if the ID is an integer!
					$newid = $res[0]['maxkey'] + 1;
					if ($newid>$id) {
						$id = $newid;
					}
					$this->connection->setSequenceId($root_table, $id);
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
	 * This cannot be called on an object that is not attached to this TDBMService
	 * (will throw a TDBMInvalidOperationException)
	 *
	 * @param AbstractTDBMObject $object the object to delete.
	 * @throws TDBMException
	 * @throws TDBMInvalidOperationException
	 */
	public function delete(AbstractTDBMObject $object) {
		switch ($object->_getStatus()) {
			case TDBMObjectStateEnum::STATE_DELETED:
				// Nothing to do, object already deleted.
				return;
			case TDBMObjectStateEnum::STATE_DETACHED:
				throw new TDBMInvalidOperationException('Cannot delete a detached object');
			case TDBMObjectStateEnum::STATE_NEW:
				foreach ($object->_getDbRows() as $dbRow) {
					$this->removeFromToSaveObjectList($dbRow);
				}
				break;
			case TDBMObjectStateEnum::STATE_DIRTY:
				foreach ($object->_getDbRows() as $dbRow) {
					$this->removeFromToSaveObjectList($dbRow);
				}
			case TDBMObjectStateEnum::STATE_NOT_LOADED:
			case TDBMObjectStateEnum::STATE_LOADED:
				// Let's delete db rows, in reverse order.
				foreach (array_reverse($object->_getDbRows()) as $dbRow) {
					$tableName = $dbRow->_getDbTableName();
					$primaryKeys = $dbRow->_getPrimaryKeys();

					$this->connection->delete($tableName, $primaryKeys);

					$this->objectStorage->remove($dbRow->_getDbTableName(), $this->getObjectHash($primaryKeys));
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
     * This function removes the given object from the database. It will also remove all objects relied to the one given
     * by parameter before all.
     *
     * Notice: if the object has a multiple primary key, the function will not work.
     *
     * @param AbstractTDBMObject $objToDelete
     */
    public function deleteCascade(AbstractTDBMObject $objToDelete) {
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
        $tableFrom = $this->connection->escapeDBItem($obj->_getDbTableName());
        $constraints = $this->connection->getConstraintsFromTable($tableFrom);
        foreach ($constraints as $constraint) {
            $tableTo = $this->connection->escapeDBItem($constraint["table1"]);
            $colFrom = $this->connection->escapeDBItem($constraint["col2"]);
            $colTo = $this->connection->escapeDBItem($constraint["col1"]);
            $idVarName = $this->connection->escapeDBItem($obj->getPrimaryKey()[0]);
            $idValue = $this->connection->quoteSmart($obj->TDBMObject_id);
            $sql = "SELECT DISTINCT ".$tableTo.".*"
                    ." FROM ".$tableFrom
                    ." LEFT JOIN ".$tableTo." ON ".$tableFrom.".".$colFrom." = ".$tableTo.".".$colTo
                    ." WHERE ".$tableFrom.".".$idVarName."=".$idValue;
            $result = $this->getObjectsFromSQL($constraint["table1"], $sql);
            foreach ($result as $tdbmObj) {
                $this->deleteCascade($tdbmObj);
            }
        }
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
	public function completeSave() {

		foreach ($this->toSaveObjects as $object)
		{
			if (!$object->db_onerror && $object->db_autosave)
			{
				$this->save($object);
			}
		}

	}

	/**
	 * This function performs a save() of all the objects that have been modified just before the program exits.
	 * It should never be called by the user, the program will call it directly.
	 *
	 */
	/*public function completeSaveOnExit() {
		$this->is_program_exiting = true;
		$this->completeSave();

		// Now, let's commit or rollback if needed.
		if ($this->connection != null && $this->connection->hasActiveTransaction()) {
			if ($this->commitOnQuit) {
				try  {
					$this->connection->commit();
				} catch (Exception $e) {
					echo $e->getMessage()."<br/>";
					echo $e->getTraceAsString();
				}
			} else {
				try  {
					$this->connection->rollback();
				} catch (Exception $e) {
					echo $e->getMessage()."<br/>";
					echo $e->getTraceAsString();
				}
			}
		}
	}*/

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
	/*public function completeSaveAndFlush() {
		$this->completeSave();

		$this->objectStorage->apply(function(TDBMObject $object) {
			/* @var $object TDBMObject * /
			if (!$object->db_onerror && $object->_getStatus() == TDBMObjectStateEnum::STATE_LOADED)
			{
				$object->_setStatus(TDBMObjectStateEnum::STATE_NOT_LOADED);
			}
		});
	}
*/


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
/*	public function getObjects($table_name, $filter_bag=null, $orderby_bag=null, $from=null, $limit=null, $className=null, $hint_path=null) {
		if ($this->connection == null) {
			throw new TDBMException("Error while calling TDBMObject::getObject(): No connection has been established on the database!");
		}
		return $this->getObjectsByMode('getObjects', $table_name, $filter_bag, $orderby_bag, $from, $limit, $className, $hint_path);
	}*/



	/**
	 * Takes in input a filter_bag (which can be about anything from a string to an array of TDBMObjects... see above from documentation),
	 * and gives back a proper Filter object.
	 *
	 * @param mixed $filter_bag
	 * @return array First item: filter string, second item: parameters.
	 */
	public function buildFilterFromFilterBag($filter_bag) {
		$counter = 1;
		if ($filter_bag === null) {
			return ['', []];
		} elseif (is_string($filter_bag)) {
			return [$filter_bag, []];
		} elseif (is_array($filter_bag)) {
			$sqlParts = [];
			$parameters = [];
			foreach ($filter_bag as $column => $value) {
				$paramName = "tdbmparam".$counter;
				if (is_array($value)) {
					$sqlParts[] = $this->connection->quoteIdentifier($column)." IN :".$paramName;
				} else {
					$sqlParts[] = $this->connection->quoteIdentifier($column)." = :".$paramName;
				}
				$parameters[$paramName] = $value;
				$counter++;
			}
			return [implode(' AND ', $sqlParts), $parameters];
		} elseif ($filter_bag instanceof AbstractTDBMObject) {
			// TODO
			throw new TDBMException("Missing feature!");
		} elseif ($filter_bag instanceof \Iterator) {
			return $this->buildFilterFromFilterBag(iterator_to_array($filter_bag));
		} else {
			throw new TDBMException("Error in filter. An object has been passed that is neither a SQL string, nor an array, nor a bean, nor null.");
		}

//		// First filter_bag should be an array, if it is a singleton, let's put it in an array.
//		if ($filter_bag === null) {
//			$filter_bag = array();
//		} elseif (!is_array($filter_bag)) {
//			$filter_bag = array($filter_bag);
//		}
//		elseif (is_a($filter_bag, 'Mouf\\Database\\TDBM\\TDBMObjectArray')) {
//			$filter_bag = array($filter_bag);
//		}
//
//		// Second, let's take all the objects out of the filter bag, and let's make filters from them
//		$filter_bag2 = array();
//		foreach ($filter_bag as $thing) {
//			if (is_a($thing,'Mouf\\Database\\TDBM\\Filters\\FilterInterface')) {
//				$filter_bag2[] = $thing;
//			} elseif (is_string($thing)) {
//				$filter_bag2[] = new SqlStringFilter($thing);
//			} elseif (is_a($thing,'Mouf\\Database\\TDBM\\TDBMObjectArray') && count($thing)>0) {
//				// Get table_name and column_name
//				$filter_table_name = $thing[0]->_getDbTableName();
//				$filter_column_names = $thing[0]->getPrimaryKey();
//
//				// If there is only one primary key, we can use the InFilter
//				if (count($filter_column_names)==1) {
//					$primary_keys_array = array();
//					$filter_column_name = $filter_column_names[0];
//					foreach ($thing as $TDBMObject) {
//						$primary_keys_array[] = $TDBMObject->$filter_column_name;
//					}
//					$filter_bag2[] = new InFilter($filter_table_name, $filter_column_name, $primary_keys_array);
//				}
//				// else, we must use a (xxx AND xxx AND xxx) OR (xxx AND xxx AND xxx) OR (xxx AND xxx AND xxx)...
//				else
//				{
//					$filter_bag_and = array();
//					foreach ($thing as $TDBMObject) {
//						$filter_bag_temp_and=array();
//						foreach ($filter_column_names as $pk) {
//							$filter_bag_temp_and[] = new EqualFilter($TDBMObject->_getDbTableName(), $pk, $TDBMObject->$pk);
//						}
//						$filter_bag_and[] = new AndFilter($filter_bag_temp_and);
//					}
//					$filter_bag2[] = new OrFilter($filter_bag_and);
//				}
//
//
//			} elseif (!is_a($thing,'Mouf\\Database\\TDBM\\TDBMObjectArray') && $thing!==null) {
//				throw new TDBMException("Error in filter bag in getObjectsByFilter. An object has been passed that is neither a filter, nor a TDBMObject, nor a TDBMObjectArray, nor a string, nor null.");
//			}
//		}
//
//		// Third, let's take all the filters and let's apply a huge AND filter
//		$filter = new AndFilter($filter_bag2);
//
//		return $filter;
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
	 * @param string $table
	 * @return string[]
	 */
	public function getPrimaryKeyColumns($table) {
		if (!isset($this->primaryKeysColumns[$table]))
		{
			$this->primaryKeysColumns[$table] = $this->tdbmSchemaAnalyzer->getSchema()->getTable($table)->getPrimaryKeyColumns();

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
	public function _addToCache(DbRow $dbRow) {
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
	private function removeFromToSaveObjectList(DbRow $myObject) {
		// TODO: replace this by a SplObjectStorage!!! Much more efficient on search!!!!
		foreach ($this->toSaveObjects as $id=>$object) {
			if ($object == $myObject)
			{
				unset($this->toSaveObjects[$id]);
				break;
			}
		}
	}

	/**
	 * This is an internal function, you should not use it in your application.
	 * This is used internally by TDBM to add an object to the list of objects that have been
	 * created/updated but not saved yet.
	 *
	 * @param AbstractTDBMObject $myObject
	 */
	public function _addToToSaveObjectList(DbRow $myObject) {
		$this->toSaveObjects[] = $myObject;
	}

	/**
	 * Generates all the daos and beans.
	 *
	 * @param string $daoFactoryClassName The classe name of the DAO factory
	 * @param string $daonamespace The namespace for the DAOs, without trailing \
	 * @param string $beannamespace The Namespace for the beans, without trailing \
	 * @param bool $support If the generated daos should keep support for old functions (eg : getUserList and getList)
	 * @param bool $storeInUtc If the generated daos should store the date in UTC timezone instead of user's timezone.
	 * @param bool $castDatesToDateTime
	 * @return \string[] the list of tables
	 */
	public function generateAllDaosAndBeans($daoFactoryClassName, $daonamespace, $beannamespace, $support, $storeInUtc, $castDatesToDateTime) {
		$tdbmDaoGenerator = new TDBMDaoGenerator($this->schemaAnalyzer, $this->tdbmSchemaAnalyzer->getSchema());
		return $tdbmDaoGenerator->generateAllDaosAndBeans($daoFactoryClassName, $daonamespace, $beannamespace, $support, $storeInUtc, $castDatesToDateTime);
	}

	/**
 	* @param array<string, string> $tableToBeanMap
 	*/
	public function setTableToBeanMap(array $tableToBeanMap) {
		$this->tableToBeanMap = $tableToBeanMap;
	}

	/**
	 * Saves $object by INSERTing or UPDAT(E)ing it in the database.
	 *
	 * @param AbstractTDBMObject $object
	 * @throws TDBMException
	 * @throws \Exception
	 */
	public function save(AbstractTDBMObject $object) {
		$status = $object->_getStatus();

		// Let's attach this object if it is in detached state.
		if ($status === TDBMObjectStateEnum::STATE_DETACHED) {
			$object->_attach($this);
			$status = $object->_getStatus();
		}

		if ($status === TDBMObjectStateEnum::STATE_NEW) {
			$dbRows = $object->_getDbRows();

			$unindexedPrimaryKeys = array();

			foreach ($dbRows as $dbRow) {

				$tableName = $dbRow->_getDbTableName();

				$schema = $this->tdbmSchemaAnalyzer->getSchema();
				$tableDescriptor = $schema->getTable($tableName);

				$primaryKeyColumns = $this->getPrimaryKeyColumns($tableName);

				if (empty($unindexedPrimaryKeys)) {
					$primaryKeys = $this->getPrimaryKeysForObjectFromDbRow($dbRow);
				} else {
					// First insert, the children must have the same primary key as the parent.
					$primaryKeys = $this->_getPrimaryKeysFromIndexedPrimaryKeys($tableName, $unindexedPrimaryKeys);
					$dbRow->_setPrimaryKeys($primaryKeys);
				}

				$references = $dbRow->_getReferences();

				// Let's save all references in NEW state (we need their primary key)
				foreach ($references as $fkName => $reference) {
					if ($reference->_getStatus() === TDBMObjectStateEnum::STATE_NEW) {
						$this->save($reference);
					}
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

				foreach ($dbRowData as $columnName => $value) {
					$columnDescriptor = $tableDescriptor->getColumn($columnName);
					$types[] = $columnDescriptor->getType();
				}

				$this->connection->insert($tableName, $dbRowData, $types);

				if (!$isPkSet && count($primaryKeyColumns) == 1) {
					$id = $this->connection->lastInsertId();
					$primaryKeys[$primaryKeyColumns[0]] = $id;
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
					if ($reference->_getStatus() === TDBMObjectStateEnum::STATE_NEW) {
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

				foreach ($dbRowData as $columnName => $value) {
					$columnDescriptor = $tableDescriptor->getColumn($columnName);
					$types[] = $columnDescriptor->getType();
				}
				foreach ($primaryKeys as $columnName => $value) {
					$columnDescriptor = $tableDescriptor->getColumn($columnName);
					$types[] = $columnDescriptor->getType();
				}

				$this->connection->update($tableName, $dbRowData, $primaryKeys, $types);

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
			throw new TDBMInvalidOperationException("This object has been deleted. It cannot be saved.");
		}
	}

	/**
	 * Returns a unique hash used to store the object based on its primary key.
	 * If the array contains only one value, then the value is returned.
	 * Otherwise, a hash representing the array is returned.
	 *
	 * @param array $primaryKeys An array of columns => values forming the primary key
	 * @return string
	 */
	public function getObjectHash(array $primaryKeys) {
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
	 * @return array Returns an array of column => value
	 */
	public function getPrimaryKeysForObjectFromDbRow(DbRow $dbRow) {
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
	 * @return array
	 */
	public function _getPrimaryKeysFromObjectData($table, array $columns) {
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
	 * @throws TDBMInvalidOperationException
	 */
	public function attach(AbstractTDBMObject $object) {
		$object->_attach($this);
	}

	/**
	 * Returns an associative array (column => value) for the primary keys from the table name and an
	 * indexed array of primary key values.
	 *
	 * @param string $tableName
	 * @param array $indexedPrimaryKeys
	 */
	public function _getPrimaryKeysFromIndexedPrimaryKeys($tableName, array $indexedPrimaryKeys) {
		$primaryKeyColumns = $this->tdbmSchemaAnalyzer->getSchema()->getTable($tableName)->getPrimaryKeyColumns();

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
	 * @return string[]
	 */
	public function _getLinkBetweenInheritedTables(array $tables)
	{
		sort($tables);
		return $this->fromCache($this->cachePrefix.'_linkbetweeninheritedtables_'.implode('__split__', $tables),
			function() use ($tables) {
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
	 * @return string[]
	 */
	private function _getLinkBetweenInheritedTablesWithoutCache(array $tables) {
		$schemaAnalyzer = $this->schemaAnalyzer;

		foreach ($tables as $currentTable) {
			$allParents = [ $currentTable ];
			$currentFk = null;
			while ($currentFk = $schemaAnalyzer->getParentRelationship($currentTable)) {
				$currentTable = $currentFk->getForeignTableName();
				$allParents[] = $currentTable;
			};

			// Now, does the $allParents contain all the tables we want?
			$notFoundTables = array_diff($tables, $allParents);
			if (empty($notFoundTables)) {
				// We have a winner!
				return $allParents;
			}
		}

		throw new TDBMException(sprintf("The tables (%s) cannot be linked by an inheritance relationship.", implode(', ', $tables)));
	}

	/**
	 * Returns the list of tables related to this table (via a parent or child inheritance relationship)
	 * @param string $table
	 * @return string[]
	 */
	public function _getRelatedTablesByInheritance($table)
	{
		return $this->fromCache($this->cachePrefix."_relatedtables_".$table, function() use ($table) {
			return $this->_getRelatedTablesByInheritanceWithoutCache($table);
		});
	}

	/**
	 * Returns the list of tables related to this table (via a parent or child inheritance relationship)
	 * @param string $table
	 * @return string[]
	 */
	private function _getRelatedTablesByInheritanceWithoutCache($table) {
		$schemaAnalyzer = $this->schemaAnalyzer;


		// Let's scan the parent tables
		$currentTable = $table;

		$parentTables = [ ];

		// Get parent relationship
		while ($currentFk = $schemaAnalyzer->getParentRelationship($currentTable)) {
			$currentTable = $currentFk->getForeignTableName();
			$parentTables[] = $currentTable;
		};

		// Let's recurse in children
		$childrenTables = $this->exploreChildrenTablesRelationships($schemaAnalyzer, $table);

		return array_merge($parentTables, $childrenTables);
	}

	/**
	 * Explore all the children and descendant of $table and returns ForeignKeyConstraints on those.
	 *
	 * @param string $table
	 * @return string[]
	 */
	private function exploreChildrenTablesRelationships(SchemaAnalyzer $schemaAnalyzer, $table) {
		$tables = [$table];
		$keys = $schemaAnalyzer->getChildrenRelationships($table);

		foreach ($keys as $key) {
			$tables = array_merge($tables, $this->exploreChildrenTablesRelationships($schemaAnalyzer, $key->getLocalTableName()));
		}

		return $tables;
	}

	/**
	 * Casts a foreign key into SQL, assuming table name is used with no alias.
	 * The returned value does contain only one table. For instance:
	 *
	 * " LEFT JOIN table2 ON table1.id = table2.table1_id"
	 *
	 * @param ForeignKeyConstraint $fk
	 * @param bool $leftTableIsLocal
	 * @return string
	 */
	/*private function foreignKeyToSql(ForeignKeyConstraint $fk, $leftTableIsLocal) {
		$onClauses = [];
		$foreignTableName = $this->connection->quoteIdentifier($fk->getForeignTableName());
		$foreignColumns = $fk->getForeignColumns();
		$localTableName = $this->connection->quoteIdentifier($fk->getLocalTableName());
		$localColumns = $fk->getLocalColumns();
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
	 * Returns an identifier for the group of tables passed in parameter.
	 *
	 * @param string[] $relatedTables
	 * @return string
	 */
	private function getTableGroupName(array $relatedTables) {
		sort($relatedTables);
		return implode('_``_', $relatedTables);
	}

	/**
	 *
	 * @param string $mainTable The name of the table queried
	 * @param string|array|null $filter The SQL filters to apply to the query (the WHERE part). All columns must be prefixed by the table name (in the form: table.column)
	 * @param array $parameters
	 * @param string|null $orderString The ORDER BY part of the query. All columns must be prefixed by the table name (in the form: table.column)
	 * @param integer $from The offset
	 * @param integer $limit The maximum number of rows returned
	 * @param array $additionalTablesFetch
	 * @param string $className Optional: The name of the class to instantiate. This class must extend the TDBMObject class. If none is specified, a TDBMObject instance will be returned.
	 * @return ResultIterator An object representing an array of results.
	 * @throws TDBMException
	 */
	public function findObjects($mainTable, $filter=null, array $parameters = array(), $orderString=null, array $additionalTablesFetch = array(), $mode = null, $className=null) {
		// $mainTable is not secured in MagicJoin, let's add a bit of security to avoid SQL injection.
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $mainTable)) {
			throw new TDBMException(sprintf("Invalid table name: '%s'", $mainTable));
		}

		list($filterString, $additionalParameters) = $this->buildFilterFromFilterBag($filter);

		$parameters = array_merge($parameters, $additionalParameters);

		// From the table name and the additional tables we want to fetch, let's build a list of all tables
		// that must be part of the select columns.

		$tableGroups = [];
		$allFetchedTables = $this->_getRelatedTablesByInheritance($mainTable);
		$tableGroupName = $this->getTableGroupName($allFetchedTables);
		foreach ($allFetchedTables as $table) {
			$tableGroups[$table] = $tableGroupName;
		}

		foreach ($additionalTablesFetch as $additionalTable) {
			$relatedTables = $this->_getRelatedTablesByInheritance($additionalTable);
			$tableGroupName = $this->getTableGroupName($relatedTables);
			foreach ($relatedTables as $table) {
				$tableGroups[$table] = $tableGroupName;
			}
			$allFetchedTables = array_merge($allFetchedTables, $relatedTables);
		}

		// Let's remove any duplicate
		$allFetchedTables = array_flip(array_flip($allFetchedTables));

		$columnsList = [];
		$columnDescList = [];
		$schema = $this->tdbmSchemaAnalyzer->getSchema();

		// Now, let's build the column list
		foreach ($allFetchedTables as $table) {
			foreach ($schema->getTable($table)->getColumns() as $column) {
				$columnName = $column->getName();
				$columnDescList[] = [
					'as' => $table.'____'.$columnName,
					'table' => $table,
					'column' => $columnName,
					'type' => $column->getType(),
					'tableGroup' => $tableGroups[$table]
				];
				$columnsList[] = $this->connection->quoteIdentifier($table).'.'.$this->connection->quoteIdentifier($columnName).' as '.
					$this->connection->quoteIdentifier($table.'____'.$columnName);
			}
		}

		$sql = "SELECT DISTINCT ".implode(', ', $columnsList)." FROM MAGICJOIN(".$mainTable.")";
		$countSql = "SELECT COUNT(1) FROM MAGICJOIN(".$mainTable.")";

		if (!empty($filterString)) {
			$sql .= " WHERE ".$filterString;
			$countSql .= " WHERE ".$filterString;
		}

		if (!empty($orderString)) {
			$sql .= " ORDER BY ".$orderString;
			$countSql .= " ORDER BY ".$orderString;
		}

		if ($mode !== null && $mode !== self::MODE_CURSOR && $mode !== self::MODE_ARRAY) {
			throw new TDBMException("Unknown fetch mode: '".$this->mode."'");
		}

		$mode = $mode?:$this->mode;

		return new ResultIterator($sql, $countSql, $parameters, $columnDescList, $this->objectStorage, $className, $this, $this->magicQuery, $mode);
	}

	/**
	 * @param $table
	 * @param array $primaryKeys
	 * @param array $additionalTablesFetch
	 * @param bool $lazy Whether to perform lazy loading on this object or not.
	 * @param string $className
	 * @return AbstractTDBMObject
	 * @throws TDBMException
	 */
	public function findObjectByPk($table, array $primaryKeys, array $additionalTablesFetch = array(), $lazy = false, $className=null) {
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
					$className = isset($this->tableToBeanMap[$table])?$this->tableToBeanMap[$table]:"Mouf\\Database\\TDBM\\TDBMObject";
				}

				// Let's construct the bean
				if (!isset($reflectionClassCache[$className])) {
					$reflectionClassCache[$className] = new \ReflectionClass($className);
				}
				// Let's bypass the constructor when creating the bean!
				$bean = $reflectionClassCache[$className]->newInstanceWithoutConstructor();
				/* @var $bean AbstractTDBMObject */
				$bean->_constructLazy($table, $primaryKeys, $this);
			}
		}

		// Did not find the object in cache? Let's query it!
		return $this->findObjectOrFail($table, $primaryKeys, [], $additionalTablesFetch, $className);
	}

	/**
	 * Returns a unique bean (or null) according to the filters passed in parameter.
	 *
	 * @param string $mainTable The name of the table queried
	 * @param string|null $filterString The SQL filters to apply to the query (the WHERE part). All columns must be prefixed by the table name (in the form: table.column)
	 * @param array $parameters
	 * @param array $additionalTablesFetch
	 * @param string $className Optional: The name of the class to instantiate. This class must extend the TDBMObject class. If none is specified, a TDBMObject instance will be returned.
	 * @return AbstractTDBMObject|null The object we want, or null if no object matches the filters.
	 * @throws TDBMException
	 */
	public function findObject($mainTable, $filterString=null, array $parameters = array(), array $additionalTablesFetch = array(), $className = null) {
		$objects = $this->findObjects($mainTable, $filterString, $parameters, null, $additionalTablesFetch, self::MODE_ARRAY, $className);
		$page = $objects->take(0, 2);
		$count = $page->count();
		if ($count > 1) {
			throw new DuplicateRowException("Error while querying an object for table '$mainTable': More than 1 row have been returned, but we should have received at most one.");
		} elseif ($count === 0) {
			return null;
		}
		return $objects[0];
	}

	/**
	 * Returns a unique bean according to the filters passed in parameter.
	 * Throws a NoBeanFoundException if no bean was found for the filter passed in parameter.
	 *
	 * @param string $mainTable The name of the table queried
	 * @param string|null $filterString The SQL filters to apply to the query (the WHERE part). All columns must be prefixed by the table name (in the form: table.column)
	 * @param array $parameters
	 * @param array $additionalTablesFetch
	 * @param string $className Optional: The name of the class to instantiate. This class must extend the TDBMObject class. If none is specified, a TDBMObject instance will be returned.
	 * @return AbstractTDBMObject The object we want
	 * @throws TDBMException
	 */
	public function findObjectOrFail($mainTable, $filterString=null, array $parameters = array(), array $additionalTablesFetch = array(), $className = null) {
		$bean = $this->findObject($mainTable, $filterString, $parameters, $additionalTablesFetch, $className);
		if ($bean === null) {
			throw new NoBeanFoundException("No result found for query on table '".$mainTable."'");
		}
		return $bean;
	}

	/**
	 * @param array $beanData An array of data: array<table, array<column, value>>
	 * @return array an array with first item = class name and second item = table name
	 */
	public function _getClassNameFromBeanData(array $beanData) {
		if (count($beanData) === 1) {
			$tableName = array_keys($beanData)[0];
		} else {
			foreach ($beanData as $table => $row) {
				$tables = [];
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
			$allTables = $this->_getLinkBetweenInheritedTables($tables);
			$tableName = $allTables[0];
		}

		// Only one table in this bean. Life is sweat, let's look at its type:
		if (isset($this->tableToBeanMap[$tableName])) {
			return [$this->tableToBeanMap[$tableName], $tableName];
		} else {
			return ["Mouf\\Database\\TDBM\\TDBMObject", $tableName];
		}
	}

	/**
	 * Returns an item from cache or computes it using $closure and puts it in cache.
	 *
	 * @param string   $key
	 * @param callable $closure
	 *
	 * @return mixed
	 */
	private function fromCache($key, callable $closure)
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
	 * @param string $table
	 * @param string $fkName
	 * @return ForeignKeyConstraint
	 */
	public function _getForeignKeyByName($table, $fkName) {
		return $this->tdbmSchemaAnalyzer->getSchema()->getTable($table)->getForeignKey($fkName);
	}
}
