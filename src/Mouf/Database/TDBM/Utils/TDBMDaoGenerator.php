<?php
namespace Mouf\Database\TDBM\Utils;

use Mouf\Database\DBConnection\ConnectionInterface;


/**
 * This class generates automatically DAOs and Beans for TDBM.
 *
 */
class TDBMDaoGenerator {

	/**
	 * 
	 * @var ConnectionInterface
	 */
	private $dbConnection;
	
	private $daoFactoryClassName;
	
	/**
	 * The namespace for the DAOs, without trailing \
	 * @var string
	 */
	private $daoNamespace;
	
	/**
	 * The Namespace for the beans, without trailing \
	 * @var string
	 */
	private $beanNamespace;
	
	/**
	 * The source directory for the files (root of the PSR-0), relative to ROOT_PATH, with no trailing or ending /
	 * @var string
	 */
	private $sourceDirectory;
	
	/**
	 * The directory for the DAOs, full path with ending /
	 * @var string
	 */
	private $daoDirectory;
	
	/**
	 * The directory for the beans, full path with ending /
	 * @var string
	 */
	private $beanDirectory;
	
	/**
	 * If the generated daos should keep support for old functions (eg : getUserList and getList)
	 * @var boolean
	 */
	private $support;
	
	/**
	 * Constructor.
	 *
	 * @param ConnectionInterface $dbConnection The connection to the database.
	 */
	public function __construct(ConnectionInterface $dbConnection, $daoFactoryClassName, $sourcedirectory, $daonamespace, $beannamespace, $support) {
		$this->dbConnection = $dbConnection;
		$this->daoFactoryClassName = $daoFactoryClassName;
		//$this->daodirectory = $daodirectory;
		//$this->beandirectory = $beandirectory;
		$this->daoNamespace = $daonamespace;
		$this->beanNamespace = $beannamespace;
		$this->sourceDirectory = $sourcedirectory;
		$this->beanDirectory = dirname(__FILE__)."/../../../../../../../../".$sourcedirectory."/".str_replace("\\", "/", $beannamespace)."/";
		$this->daoDirectory = dirname(__FILE__)."/../../../../../../../../".$sourcedirectory."/".str_replace("\\", "/", $daonamespace)."/";
		$this->support = $support;
	}
	
	/**
	 * Generates all the daos and beans.
	 */
	public function generateAllDaosAndBeans() {
		// TODO: check that no class name ends with "Base". Otherwise, there will be name clash.
		if (!file_exists($this->daoDirectory)) {
			$result = mkdir($this->daoDirectory, 0777, true);
			if ($result == false) {
				echo "Unable to create directory: ".$this->daoDirectory.".";
				exit;
			}
		}
		if (!file_exists($this->beanDirectory)) {
			$result = mkdir($this->beanDirectory, 0777, true);
			if ($result == false) {
				echo "Unable to create directory: ".$this->beanDirectory.".";
				exit;
			}
		}
		
		$tableList = $this->dbConnection->getListOfTables();
		foreach ($tableList as $table) {
			$this->generateDaoAndBean($table);
		}
		
		$this->generateFactory($tableList);

		// Ok, let's return the list of all tables.
		// These will be used by the calling script to create Mouf instances.
		$root = simplexml_load_string("<tdbm></tdbm>");
    	foreach ($tableList as $table) {
    		$root->addChild("table", $table);
    	}
		
		return $root;
	}
	
	/**
	 * Generates in one method call the daos and the beans for one table.
	 * 
	 * @param $tableName
	 */
	public function generateDaoAndBean($tableName) {
		//$baseClassName = TDBMDaoGenerator::toSingular(TDBMDaoGenerator::toCamelCase($tableName));
		$daoName = $this->getDaoNameFromTableName($tableName);
		$beanName = $this->getBeanNameFromTableName($tableName);
		$baseBeanName = $this->getBaseBeanNameFromTableName($tableName);
		
		$this->generateBean($beanName.".php", $beanName, $baseBeanName.".php", $baseBeanName, $tableName);
		$this->generateDao($daoName.".php", $daoName."Base.php", $beanName.".php", $daoName, $daoName."Base", $beanName, $tableName);
	}
	
	/**
	 * Returns the name of the bean class from the table name.
	 * 
	 * @param $tableName
	 * @return string
	 */
	public static function getBeanNameFromTableName($tableName) {
		return TDBMDaoGenerator::toSingular(TDBMDaoGenerator::toCamelCase($tableName))."Bean";
	}
	
	/**
	 * Returns the name of the base bean class from the table name.
	 * 
	 * @param $tableName
	 * @return string
	 */
	public static function getDaoNameFromTableName($tableName) {
		return TDBMDaoGenerator::toSingular(TDBMDaoGenerator::toCamelCase($tableName))."Dao";
	}
	
	/**
	 * Returns the name of the DAO class from the table name.
	 * 
	 * @param $tableName
	 * @return string
	 */
	public static function getBaseBeanNameFromTableName($tableName) {
		return TDBMDaoGenerator::toSingular(TDBMDaoGenerator::toCamelCase($tableName))."BaseBean";
	}
	
	/**
	 * Writes the PHP bean file with all getters and setters from the table passed in parameter.
	 *
	 * @param string $fileName The file that will be written (name only, no directory)
	 * @param string $className The name of the class
	 * @param string $baseFileName The name of the base class file which contains the base file which will be extended (name only, no directory)
	 * @param string $baseClassName The name of the base class which will be extended (name only, no directory)
	 * @param string $tableName The name of the table
	 */	
	public function generateBean($fileName, $className, $baseFileName, $baseClassName, $tableName) {
		//$tableInfo = $this->dbConnection->getTableInfo($tableName);
		$table = $this->dbConnection->getTableFromDbModel($tableName);

		// List of methods already written.
		$methodsList = array();
		
		$str = "<?php
namespace {$this->beanNamespace};

use Mouf\\Database\\TDBM\\TDBMObject;

/*
 * This file has been automatically generated by TDBM.
 * DO NOT edit this file, as it might be overwritten.
 * If you need to perform changes, edit the $className class instead!
 */

/**
 * The $baseClassName class maps the '$tableName' table in database.
 * @dbTable $tableName
 */
class $baseClassName extends TDBMObject 
{
";
		
		
		foreach ($table->columns as $column) {

			$type = $column->type;
			$normalizedType = $this->dbConnection->getUnderlyingType($type);

			$columnGetterName = self::getGetterNameForPropertyName($column->name);
			$columnSetterName = self::getSetterNameForPropertyName($column->name);
			
			$methodsList[$columnGetterName] = $columnGetterName;
			$methodsList[$columnSetterName] = $columnSetterName;			
			
			if ($normalizedType == "timestamp" || $normalizedType == "datetime" || $normalizedType == "date") {
				$str .= '	/**
	 * The getter for the "'.$column->name.'" column.
	 * It is returned as a PHP timestamp.
	 *
	 * @dbType '.$normalizedType.'
	 * @dbColumn '.$column->name.'
	 * @return timestamp
	 */
	public function '.$columnGetterName.'(){
		$date = $this->__get(\''.$column->name.'\');
		if($date === null)
			return null;
		else
			return strtotime($date);
	}
	
	/**
	 * The setter for the "'.$column->name.'" column.
	 * It must be provided as a PHP timestamp.
	 *
	 * @dbColumn '.$column->name.'
	 * @param timestamp $'.$column->name.'
	 */
	public function '.$columnSetterName.'($'.$column->name.') {
		if($'.$column->name.' === null)
			$this->__set(\''.$column->name.'\', null);
		else
			$this->__set(\''.$column->name.'\', date("Y-m-d H:i:s", $'.$column->name.'));
	}
	
';
			} else {
				$str .= '	/**
	 * The getter for the "'.$column->name.'" column.
	 *
	 * @dbType '.$normalizedType.'
	 * @dbColumn '.$column->name.'
	 * @return string
	 */
	public function '.$columnGetterName.'(){
		return $this->__get(\''.$column->name.'\');
	}
	
	/**
	 * The setter for the "'.$column->name.'" column.
	 *
	 * @dbColumn '.$column->name.'
	 * @param string $'.$column->name.'
	 */
	public function '.$columnSetterName.'($'.$column->name.') {
		$this->__set(\''.$column->name.'\', $'.$column->name.');
	}
	
';				
			}


		}

		$referencedTablesList = array();
		// Now, let's get the constraints from this table on another table.
		// We will generate getters and setters for those. 
		//$constraints = $this->dbConnection->getConstraintsFromTable($tableName);
		$constraints = $this->dbConnection->getConstraintsOnTable($tableName);
		
		foreach ($constraints as $array) {
			if (!isset($referencedTablesList[$array["table2"]])) {
				$referencedTablesList[$array["table2"]] = 1; 
			} else {
				$referencedTablesList[$array["table2"]] += 1;
			}
			$getterName = self::getGetterNameForConstrainedObject($array["table2"], $array["col1"]);
			$setterName = self::getSetterNameForConstrainedObject($array["table2"], $array["col1"]);
			
			// If the method has already been defined, lets not write it.
			if (isset($methodsList[$getterName]) || isset($methodsList[$setterName])) {
				continue;
			}
			$methodsList[$getterName] = $getterName;
			$methodsList[$setterName] = $setterName; 

			$referencedBeanName = $this->getBeanNameFromTableName($array["table2"]);

			$str .= '	/**
	 * Returns the '.$referencedBeanName.' object bound to this object via the '.$array["col1"].' column.
	 * 
	 * @return '.$referencedBeanName.'
	 */
	public function '.$getterName.'() {
		if ($this->'.$array["col1"].' == null) {
			return null;
		}
		return $this->tdbmService->getObject("'.$array["table2"].'", $this->'.$array["col1"].', "'.str_replace("\\","\\\\",$this->beanNamespace).'\\\\'.$referencedBeanName.'", true);
	}
	
	/**
	 * The setter for the '.$referencedBeanName.' object bound to this object via the '.$array["col1"].' column.
	 *
	 * @param '.$referencedBeanName.' $object
	 */
	public function '.$setterName.'('.$referencedBeanName.' $object = null) {
		$this->__set(\''.$array["col1"].'\', ($object == null)?null:$object->__get(\''.$array["col2"].'\'));
	}
	
';
				
		}
	
		
		// Now, let's implement the shortcuts to the getter of objects.
		// Shortcuts are used to save typing. They are available only if a referenced table is referenced only once by our tables.
		foreach($referencedTablesList as $referrencedTable=>$number) {
			if ($number == 1) {
				foreach ($constraints as $array) {
					if ($array['table2'] ==$referrencedTable) {
						$columnName = $array['col1'];
						$targetColumnName = $array['col2'];
						break;
					}
				}
				$fullGetterName = self::getGetterNameForConstrainedObject($referrencedTable, $columnName);
				$shortGetterName = self::getGetterNameForConstrainedObject($referrencedTable);
				$fullSetterName = self::getSetterNameForConstrainedObject($referrencedTable, $columnName);
				$shortSetterName = self::getSetterNameForConstrainedObject($referrencedTable);

				// If the method has already been defined, lets not write it.
				if (isset($methodsList[$shortGetterName]) || isset($methodsList[$shortSetterName])) {
					continue;
				}
				$methodsList[$shortGetterName] = $shortGetterName;
				$methodsList[$shortSetterName] = $shortSetterName; 
					
				
				$referencedBeanName = $this->getBeanNameFromTableName($array["table2"]);
				
				$str .= '	/**
	 * Returns the '.$referencedBeanName.' object bound to this object via the '.$array["col1"].' column.
	 * This is an alias for the '.$fullGetterName.' method.  
	 *
	 * @return '.$referencedBeanName.'
	 */
	public function '.$shortGetterName.'() {
		if ($this->'.$array["col1"].' == null) {
			return null;
		}
		return $this->tdbmService->getObject("'.$array["table2"].'", $this->'.$array["col1"].', "'.str_replace("\\","\\\\",$this->beanNamespace).'\\\\'.$referencedBeanName.'");
	}
	
	/**
	 * The setter for the "'.$array['col1'].'" column.
	 * This is an alias for the '.$fullSetterName.' method.
	 *
	 * @param '.$referencedBeanName.' $object
	 */
	public function '.$shortSetterName.'('.$referencedBeanName.' $object = null) {
		$this->__set(\''.$array["col1"].'\', ($object == null)?null:$object->__get(\''.$array["col2"].'\'));
	}
	
';
					
			}
		}
		
		$str .= "}
?>";
		
		file_put_contents($this->beanDirectory.$baseFileName, $str);
		

		if (!file_exists($this->beanDirectory.$fileName)) {
			$str = "<?php
/*
 * This file has been automatically generated by TDBM.
 * You can edit this file as it will not be overwritten.
 */

namespace {$this->beanNamespace};
 
/**
 * The $className class maps the '$tableName' table in database.
 * @dbTable $tableName
 *
 */
class $className extends $baseClassName 
{

}";
			file_put_contents($this->beanDirectory.$fileName ,$str);
		}
	}

	/**
	 * Writes the PHP bean DAO with simple functions to create/get/save objects.
	 *
	 * @param string $fileName The file that will be written (without the directory)
	 * @param string $className The name of the class
	 * @param string $tableName The name of the table
	 */
	public function generateDao($fileName, $baseFileName, $beanFileName, $className, $baseClassName, $beanClassName, $tableName) {
		$info = $this->dbConnection->getTableInfo($tableName);
		$defaultSort = null;
		foreach ($info as $index => $data) {
			$comments = $data['column_comment'];
			$matches = array();
			if (preg_match('/@defaultSort(\((desc|asc)\))*/', $comments, $matches) != 0){
				$defaultSort = $data['column_name'];
				if (count($matches == 3)){
					$defaultSortDirection = $matches[2];
				}else{
					$defaultSortDirection = 'ASC';
				}
			}
		}
				
		$tableCamel = self::toSingular(self::toCamelCase($tableName));
		
		$beanClassName = $this->beanNamespace."\\".$beanClassName;
		
		$str = "<?php
/*
 * This file has been automatically generated by TDBM.
 * DO NOT edit this file, as it might be overwritten.
 * If you need to perform changes, edit the $className class instead!
 */
namespace {$this->daoNamespace};

use Mouf\\Database\\DAOInterface;
use Mouf\\Database\\TDBM\\TDBMService; 
use Mouf\\Database\\TDBM\\Filters\\OrderByColumn;

/**
 * The $baseClassName class will maintain the persistance of $beanClassName class into the $tableName table.
 * 
 */
class $baseClassName implements DAOInterface
{

	/**
	 * @var TDBMService
	 */
	protected \$tdbmService;
	
	/**
	 * The default Sort column
	 * @var string
	 */
	private \$defaultSort = ".($defaultSort ? "'$defaultSort'" : 'null').";
	
	/**
	 * The default Sort direction
	 * @var string
	 */
	private \$defaultDirection = ".($defaultSort && $defaultSortDirection ? "'$defaultSortDirection'" : "'asc'").";
	
	/**
	 * Sets the TDBM service used by this DAO.
	 *
	 * @Property
	 * @Compulsory
	 * @param TDBMService \$tdbmService
	 */
	public function setTdbmService(TDBMService \$tdbmService) {
		\$this->tdbmService = \$tdbmService;
	}

	/**
	 * Return a new instance of $beanClassName object, that will be persisted in database.
	 *
	 * @return $beanClassName
	 */
	public function create() {
		return \$this->tdbmService->getNewObject('$tableName', true, '$beanClassName');
	}
	
	/**
	 * Persist the $beanClassName instance
	 *
	 */
	public function save(\$obj) {
		\$obj->save();
	}

	/**
	 * Get all $tableCamel records. 
	 *
	 * @return array<$beanClassName>
	 */
	public function getList() {
		if (\$this->defaultSort){
			\$orderBy = new OrderByColumn('$tableName', \$this->defaultSort, \$this->defaultDirection);
		}else{
			\$orderBy = null;
		}
		return \$this->tdbmService->getObjects('$tableName',  null, \$orderBy, null, null, '$beanClassName');
	}
	
	/**
	 * Get $beanClassName specified by its ID (its primary key)
	 * If the primary key does not exist, an exception is thrown.
	 *
	 * @param string \$id
	 * @param boolean \$lazyLoading If set to true, the object will not be loaded right away. Instead, it will be loaded when you first try to access a method of the object.
	 * @return $beanClassName
	 * @throws TDBMException
	 */
	public function getById(\$id, \$lazyLoading = false) {
		return \$this->tdbmService->getObject('$tableName', \$id, '$beanClassName', \$lazyLoading);
	}
	
	/**
	 * Deletes the $beanClassName passed in parameter.
	 *
	 * @param $beanClassName \$obj
	 */
	public function delete(\$obj) {
		\$this->tdbmService->deleteObject(\$obj);
	}
	
	/**
	 * Get a list of $beanClassName specified by its filters.
	 *
	 * @param mixed \$filterBag The filter bag (see TDBMService::getObjects for complete description)
	 * @param mixed \$orderbyBag The order bag (see TDBMService::getObjects for complete description)
	 * @param integer \$from The offset
	 * @param integer \$limit The maximum number of rows returned
	 * @return array<$beanClassName>
	 */
	protected function getListByFilter(\$filterBag=null, \$orderbyBag=null, \$from=null, \$limit=null) {
		if (\$this->defaultSort && \$orderbyBag == null){
			\$orderbyBag = new OrderByColumn('$tableName', \$this->defaultSort, \$this->defaultDirection);
		}
		return \$this->tdbmService->getObjects('$tableName', \$filterBag, \$orderbyBag, \$from, \$limit, '$beanClassName');
	}

	/**
	 * Get a single $beanClassName specified by its filters.
	 *
	 * @param mixed \$filterBag The filter bag (see TDBMService::getObjects for complete description)
	 * @return $beanClassName
	 */
	protected function getByFilter(\$filterBag=null) {
		return \$this->tdbmService->getObject('$tableName', \$filterBag, '$beanClassName');
	}
	
	/**
	 * Sets the default column for default sorting
	 *
	 */
	public function setDefaultSort(\$defaultSort){
		\$this->defaultSort = \$defaultSort;
	}
	";
// If we want compatibility with TDBM < 2.3
if ($this->support) {
$str .= "

	/**
	 * Return a new instance of $beanClassName object, that will be persisted in database.
	 *
	 * @return $beanClassName
	 */
	public function getNew$tableCamel() {
		return \$this->create();
	}

	/**
	 * Persist the $beanClassName instance
	 * (old function to keep compatibility with TDBM < 2.3)
	 *
	 */
	public function save$tableCamel(\$obj) {
		\$this->save(\$obj);
	}
	
	/**
	 * Get all $tableCamel records. 
	 *
	 * @return array<$beanClassName>
	 */
	public function get".$tableCamel."List() {
		return \$this->getList();
	}
	
	/**
	 * Get $beanClassName specified by its ID (its primary key)
	 * If the primary key does not exist, an exception is thrown.
	 *
	 * @param string \$id
	 * @param boolean \$lazyLoading If set to true, the object will not be loaded right away. Instead, it will be loaded when you first try to access a method of the object.
	 * @return $beanClassName
	 * @throws TDBMException
	 */
	public function get".$tableCamel."ById(\$id, \$lazyLoading = false) {
		return \$this->getById(\$id, \$lazyLoading);
	}
	
	/**
	 * Deletes the $beanClassName passed in parameter.
	 *
	 * @param $beanClassName \$obj
	 */
	public function delete".$tableCamel."(\$obj) {
		\$this->delete(\$obj);
	}
	
	/**
	 * Get a list of $beanClassName specified by its filters.
	 *
	 * @param mixed \$filterBag The filter bag (see TDBMService::getObjects for complete description)
	 * @param mixed \$orderbyBag The order bag (see TDBMService::getObjects for complete description)
	 * @param integer \$from The offset
	 * @param integer \$limit The maximum number of rows returned
	 * @return array<$beanClassName>
	 */
	protected function get".$tableCamel."ListByFilter(\$filterBag=null, \$orderbyBag=null, \$from=null, \$limit=null) {
		return \$this->getListByFilter(\$filterBag, \$orderbyBag, \$from, \$limit);
	}

	/**
	 * Get a single $beanClassName specified by its filters.
	 *
	 * @param mixed \$filterBag The filter bag (see TDBMService::getObjects for complete description)
	 * @return $beanClassName
	 */
	protected function get".$tableCamel."ByFilter(\$filterBag=null) {
		return \$this->getByFilter(\$filterBag);
	}
	";
}
$str .= "
}
?>";

		

		file_put_contents($this->daoDirectory.$baseFileName ,$str);
		
		// Now, let's generate the "editable" class
		if (!file_exists($this->daoDirectory.$fileName)) {
			$str = "<?php
/*
 * This file has been automatically generated by TDBM.
 * You can edit this file as it will not be overwritten.
 */
namespace {$this->daoNamespace};

/**
 * The $className class will maintain the persistance of $beanClassName class into the $tableName table.
 *
 * @Component
 * @dbTable $tableName
 */
class $className extends $baseClassName
{

}";
			file_put_contents($this->daoDirectory.$fileName ,$str);
		}
	}
	
	/**
	 * Generates the factory bean.
	 * 
	 * @param $tableList
	 */
	private function generateFactory($tableList) {
		// For each table, let's write a property.
		
		$str = "<?php
/*
 * This file has been automatically generated by TDBM.
 * DO NOT edit this file, as it might be overwritten.
 */
namespace {$this->daoNamespace};
		
/**
 * The $this->daoFactoryClassName provides an easy access to all DAOs generated by TDBM.
 *
 * @Component
 */
class $this->daoFactoryClassName 
{
";

		foreach ($tableList as $table) {
			$daoClassName = $this->getDaoNameFromTableName($table);
			$daoInstanceName = self::toVariableName($daoClassName);
			
			$str .= '	/**
	 * @var '.$daoClassName.'
	 */
	private $'.$daoInstanceName.';

	/**
	 * Returns an instance of the '.$daoClassName.' class.
	 * 
	 * @return '.$daoClassName.'
	 */
	public function get'.$daoClassName.'() {
		return $this->'.$daoInstanceName.';
	}
	
	/**
	 * Sets the instance of the '.$daoClassName.' class that will be returned by the factory getter.
	 * 
	 * @Property
	 * @Compulsory
	 * @param '.$daoClassName.' $'.$daoInstanceName.'
	 */
	public function set'.$daoClassName.'('.$daoClassName.' $'.$daoInstanceName.') {
		$this->'.$daoInstanceName.' = $'.$daoInstanceName.';
	}
	
';
		}
		
		
		$str .= '
}
?>';
		
		//$daoDirectory = dirname(__FILE__)."/../../../../../dao/";
		file_put_contents($this->daoDirectory.$this->daoFactoryClassName.'.php' ,$str);
	}
	
	/**
	 * Transforms the property name in a setter name.
	 * For instance, phone => getPhone or name => getName
	 *
	 * @param string $methodName
	 * @return string
	 */
	public static function getSetterNameForPropertyName($propertyName) {
		$propName2 = self::toCamelCase($propertyName);
		return "set".$propName2;
	}
	
	/**
	 * Transforms the property name in a getter name.
	 * For instance, phone => getPhone or name => getName
	 *
	 * @param string $propertyName
	 * @return string
	 */
	public static function getGetterNameForPropertyName($propertyName) {
		$propName2 = self::toCamelCase($propertyName);
		return "get".$propName2;
	}

	/**
	 * Transforms the table name constrained by this object into a setter name.
	 * For instance, users => setUserByUserId or role => setRoleByRoleId
	 *
	 * @param $tableName The table that is constrained
	 * @param $columnName The column used to constrain the table (optional). If omitted, the "By[columnname]" part of the name will be omitted.
	 * @return string
	 */
	public static function getSetterNameForConstrainedObject($tableName, $columnName = null) {
		$getter = self::toSingular(self::toCamelCase($tableName));
		if ($columnName) {
			$getter .= 'By'.self::toCamelCase($columnName);
		}
		return "set".$getter;
	}
	
	/**
	 * Transforms the table name constrained by this object into a getter name.
	 * For instance, users => getUserByUserId or role => getRoleByRoleId
	 *
	 * @param $tableName The table that is constrained
	 * @param $columnName The column used to constrain the table (optional). If omitted, the "By[columnname]" part of the name will be omitted.
	 * @return string
	 */
	public static function getGetterNameForConstrainedObject($tableName, $columnName = null) {
		$getter = self::toSingular(self::toCamelCase($tableName));
		if ($columnName) {
			$getter .= 'By'.self::toCamelCase($columnName);
		}
		return "get".$getter;
	}
	
	/**
	 * Transforms a string to camelCase (except the first letter will be uppercase too).
	 * Underscores and spaces are removed and the first letter after the underscore is uppercased.
	 * 
	 * @param $str string
	 * @return string
	 */
	public static function toCamelCase($str) {
		$str = strtoupper(substr($str,0,1)).substr($str,1);
		while (true) {
			if (strpos($str, "_") === false && strpos($str, " ") === false)
				break;
				
			$pos = strpos($str, "_");
			if ($pos === false) {
				$pos = strpos($str, " ");
			}
			$before = substr($str,0,$pos);
			$after = substr($str,$pos+1);
			$str = $before.strtoupper(substr($after,0,1)).substr($after,1);
		}
		return $str;
	}
	
	/**
	 * Tries to put string to the singular form (if it is plural).
	 * Obviously, this can't be perfect, be we do the best we can.
	 * 
	 * @param $str string
	 * @return string
	 */
	public static function toSingular($str) {
		// First, ignore "ss" words (like access).
		if (strpos($str, "ss", strlen($str)-2) !== false) {
			return $str;
		}
		
		// Now, let's see if the string ends with s:
		if (strpos($str, "s", strlen($str)-1) !== false) {
			// Yes? Let's remove the s.
			return substr($str, 0, strlen($str)-1);
		}
		return $str;
	}
	
	/**
	 * Put the first letter of the string in lower case.
	 * Very useful to transform a class name into a variable name.
	 * 
	 * @param $str string
	 * @return string
	 */
	public static function toVariableName($str) {
		return strtolower(substr($str, 0, 1)).substr($str, 1);
	}
}
?>