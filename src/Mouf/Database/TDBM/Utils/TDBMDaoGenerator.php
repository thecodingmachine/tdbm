<?php
namespace Mouf\Database\TDBM\Utils;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Mouf\Composer\ClassNameMapper;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use Mouf\Database\TDBM\TDBMException;



/**
 * This class generates automatically DAOs and Beans for TDBM.
 *
 */
class TDBMDaoGenerator {

    /**
     * 
     * @var SchemaAnalyzer
     */
    private $schemaAnalyzer;

    /**
     *
     * @var Schema
     */
    private $schema;

    /**
     * The root directory of the project.
     *
     * @var string
     */
    private $rootPath;

    /**
     * Constructor.
     *
     * @param Connection $dbConnection The connection to the database.
     */
    public function __construct(SchemaAnalyzer $schemaAnalyzer, Schema $schema) {
        $this->schemaAnalyzer = $schemaAnalyzer;
        $this->schema = $schema;
        $this->rootPath = __DIR__."/../../../../../../../../";
    }

    /**
     * Generates all the daos and beans.
     *
     * @param string $daoFactoryClassName The classe name of the DAO factory
     * @param string $daonamespace The namespace for the DAOs, without trailing \
     * @param string $beannamespace The Namespace for the beans, without trailing \
     * @param bool $storeInUtc If the generated daos should store the date in UTC timezone instead of user's timezone.
     * @return \string[] the list of tables
     * @throws TDBMException
     */
    public function generateAllDaosAndBeans($daoFactoryClassName, $daonamespace, $beannamespace, $storeInUtc) {
        // TODO: extract ClassNameMapper in its own package!
        $classNameMapper = ClassNameMapper::createFromComposerFile($this->rootPath.'composer.json');

        // TODO: check that no class name ends with "Base". Otherwise, there will be name clash.

        $tableList = $this->schema->getTables();
        foreach ($tableList as $table) {
            $this->generateDaoAndBean($table, $daonamespace, $beannamespace, $classNameMapper, $storeInUtc);
        }
        
        $this->generateFactory($tableList, $daoFactoryClassName, $daonamespace, $classNameMapper);

        // Ok, let's return the list of all tables.
        // These will be used by the calling script to create Mouf instances.
        
        return array_map(function(Table $table) { return $table->getName(); },$tableList);
    }
    
    /**
     * Generates in one method call the daos and the beans for one table.
     * 
     * @param $tableName
     */
    public function generateDaoAndBean(Table $table, $daonamespace, $beannamespace, ClassNameMapper $classNameMapper, $storeInUtc) {
		$tableName = $table->getName();
        $daoName = $this->getDaoNameFromTableName($tableName);
        $beanName = $this->getBeanNameFromTableName($tableName);
        $baseBeanName = $this->getBaseBeanNameFromTableName($tableName);
        $baseDaoName = $this->getBaseDaoNameFromTableName($tableName);

        $this->generateBean($beanName, $baseBeanName, $table, $beannamespace, $classNameMapper, $storeInUtc);
        $this->generateDao($daoName, $baseDaoName, $beanName, $table, $daonamespace, $beannamespace, $classNameMapper);
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
     * Returns the name of the DAO class from the table name.
     * 
     * @param $tableName
     * @return string
     */
    public static function getDaoNameFromTableName($tableName) {
        return TDBMDaoGenerator::toSingular(TDBMDaoGenerator::toCamelCase($tableName))."Dao";
    }
    
    /**
     * Returns the name of the base bean class from the table name.
     * 
     * @param $tableName
     * @return string
     */
    public static function getBaseBeanNameFromTableName($tableName) {
        return TDBMDaoGenerator::toSingular(TDBMDaoGenerator::toCamelCase($tableName))."BaseBean";
    }

    /**
     * Returns the name of the base DAO class from the table name.
     *
     * @param $tableName
     * @return string
     */
    public static function getBaseDaoNameFromTableName($tableName) {
        return TDBMDaoGenerator::toSingular(TDBMDaoGenerator::toCamelCase($tableName))."BaseDao";
    }

    /**
     * Writes the PHP bean file with all getters and setters from the table passed in parameter.
     *
     * @param string $className The name of the class
     * @param string $baseClassName The name of the base class which will be extended (name only, no directory)
     * @param string $tableName The name of the table
     * @param string $beannamespace The namespace of the bean
     * @param ClassNameMapper $classNameMapper
     * @throws TDBMException
     */
    public function generateBean($className, $baseClassName, Table $table, $beannamespace, ClassNameMapper $classNameMapper, $storeInUtc) {
		$tableName = $table->getName();

		$parentFk = $this->schemaAnalyzer->getParentRelationship($tableName);
		if ($parentFk !== null) {
			$extends = $this->getBeanNameFromTableName($parentFk->getForeignTableName());
			$use = "";
		} else {
			$extends = "AbstractTDBMObject";
			$use = "use Mouf\\Database\\TDBM\\AbstractTDBMObject;\n\n";
		}

        // List of methods already written.
        $methodsList = array();
        
        $str = "<?php
namespace {$beannamespace};

$use
/*
 * This file has been automatically generated by TDBM.
 * DO NOT edit this file, as it might be overwritten.
 * If you need to perform changes, edit the $className class instead!
 */

/**
 * The $baseClassName class maps the '$tableName' table in database.
 */
class $baseClassName extends $extends
{
";
        
        
        foreach ($this->getColumnsOutOfForeignKey($table) as $column) {
            $type = $column->getType();
            $normalizedType = $this->dbalTypeToPhpType($type);

            $columnGetterName = self::getGetterNameForPropertyName($column->getName());
            $columnSetterName = self::getSetterNameForPropertyName($column->getName());
            
            $methodsList[$columnGetterName] = $columnGetterName;
            $methodsList[$columnSetterName] = $columnSetterName;
            
            if ($normalizedType == "\\DateTimeInterface") {
                $castTo = "\\DateTimeInterface ";
            } else {
                $castTo = "";
            }

			$getterAndSetterCode = '    /**
     * The getter for the "%s" column.
     *
     * @return %s
     */
    public function %s() {
        return $this->get(\'%s\', \'%s\');
    }

    /**
     * The setter for the "%s" column.
     *
     * @param %s $%s
     */
    public function %s(%s$%s) {
        $this->set(\'%s\', $%s, %s);
    }

';
			$str .= sprintf($getterAndSetterCode,
				// Getter
				$column->getName(),
				$normalizedType,
				$columnGetterName,
				$column->getName(),
				$table->getName(),
				// Setter
				$column->getName(),
				$normalizedType,
				$column->getName(),
				$columnSetterName,
				$castTo,
				$column->getName(),
				$column->getName(),
				$column->getName(),
				$table->getName()
				);
		}

        // Now, let's get the constraints from this table on another table.
        // We will generate getters and setters for those. 
        //$constraints = $this->dbConnection->getConstraintsFromTable($tableName);
        $foreignKeys = $table->getForeignKeys();
        
        foreach ($foreignKeys as $foreignKey) {
			if ($parentFk !== null && $foreignKey->getForeignTableName() === $parentFk->getForeignTableName()
				&& $foreignKey->getLocalColumns() === $parentFk->getLocalColumns()
				&& $foreignKey->getForeignColumns() === $parentFk->getForeignColumns()) {
				// Let's ignore the parent relationships.
				continue;
			}
            $getterName = self::getMethodNameForConstrainedObject($foreignKey, $methodsList, "get");
			$methodsList[$getterName] = $getterName;
            $setterName = self::getMethodNameForConstrainedObject($foreignKey, $methodsList, "set");
			$methodsList[$setterName] = $setterName;

            $referencedBeanName = $this->getBeanNameFromTableName($foreignKey->getForeignTableName());

            $str .= '    /**
     * Returns the '.$referencedBeanName.' object bound to this object via the '.implode(" and ", $foreignKey->getLocalColumns()).' column.
     * 
     * @return '.$referencedBeanName.'
     */
    public function '.$getterName.'() {';

			foreach ($foreignKey->getLocalColumns() as $column) {
				$str .= '
        if ($this->get('.var_export($column, true).', '.var_export($tableName, true).') == null) {
            return null;
        }';
			}

			$str .= '
        return $this->tdbmService->findObjectByPk("'.$foreignKey->getForeignTableName().'", [
';
			$foreignColumns = $foreignKey->getForeignColumns();
			foreach ($foreignKey->getLocalColumns() as $key => $column) {
				$str .= '            '. var_export($foreignColumns[$key], true) .' => $this->get('.var_export($column, true).', '.var_export($tableName, true).'),'."\n";
			}
			$str .='
        ], [], true);
    }

    /**
     * The setter for the '.$referencedBeanName.' object bound to this object via the '.implode(" and ", $foreignKey->getLocalColumns()).' column.
     *
     * @param '.$referencedBeanName.' $object
     */
    public function '.$setterName.'('.$referencedBeanName.' $object = null) {
';
			$foreignColumns = $foreignKey->getForeignColumns();
			foreach ($foreignKey->getLocalColumns() as $key=>$column) {
				$str .= '

        $this->__set(' . var_export($column, true) . ', ($object === null)?null:$object->__get(' . var_export($foreignColumns[$key], true).', '.var_export($foreignKey->getForeignTableName(), true).'));
        ';
			}
			$str .= '
    }
    
';
                
        }
    
        

        $str .= "}
";

        $possibleBaseFileNames = $classNameMapper->getPossibleFileNames($beannamespace."\\".$baseClassName);
        if (!$possibleBaseFileNames) {
            throw new TDBMException('Sorry, autoload namespace issue. The class "'.$beannamespace."\\".$baseClassName.'" is not autoloadable.');
        }
        $possibleBaseFileName = $this->rootPath.$possibleBaseFileNames[0];

        $this->ensureDirectoryExist($possibleBaseFileName);
        file_put_contents($possibleBaseFileName, $str);
        @chmod($possibleBaseFileName, 0664);



        $possibleFileNames = $classNameMapper->getPossibleFileNames($beannamespace."\\".$className);
        if (!$possibleFileNames) {
            // @codeCoverageIgnoreStart
            throw new TDBMException('Sorry, autoload namespace issue. The class "'.$beannamespace."\\".$className.'" is not autoloadable.');
            // @codeCoverageIgnoreEnd
        }
        $possibleFileName = $this->rootPath.$possibleFileNames[0];

        if (!file_exists($possibleFileName)) {
            $str = "<?php
/*
 * This file has been automatically generated by TDBM.
 * You can edit this file as it will not be overwritten.
 */

namespace {$beannamespace};
 
/**
 * The $className class maps the '$tableName' table in database.
 * @dbTable $tableName
 *
 */
class $className extends $baseClassName 
{

}";
            $this->ensureDirectoryExist($possibleFileName);
            file_put_contents($possibleFileName ,$str);
            @chmod($possibleFileName, 0664);
        }
    }

    /**
     * Writes the PHP bean DAO with simple functions to create/get/save objects.
     *
     * @param string $fileName The file that will be written (without the directory)
     * @param string $className The name of the class
     * @param string $tableName The name of the table
     */
    public function generateDao($className, $baseClassName, $beanClassName, Table $table, $daonamespace, $beannamespace, ClassNameMapper $classNameMapper) {
        $tableName = $table->getName();
        $primaryKeyColumns = $table->getPrimaryKeyColumns();

        $defaultSort = null;
        foreach ($table->getColumns() as $column) {
            $comments = $column->getComment();
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

        // FIXME: lowercase tables with _ in the name should work!
        $tableCamel = self::toSingular(self::toCamelCase($tableName));
        
        $beanClassWithoutNameSpace = $beanClassName;
        $beanClassName = $beannamespace."\\".$beanClassName;
        
        $str = "<?php

/*
 * This file has been automatically generated by TDBM.
 * DO NOT edit this file, as it might be overwritten.
 * If you need to perform changes, edit the $className class instead!
 */

namespace {$daonamespace};

use Mouf\\Database\\TDBM\\TDBMService;
use Mouf\\Database\\TDBM\\ResultIterator;
use Mouf\\Database\\TDBM\\ArrayIterator;
use $beanClassName;

/**
 * The $baseClassName class will maintain the persistence of $beanClassWithoutNameSpace class into the $tableName table.
 * 
 */
class $baseClassName
{

    /**
     * @var TDBMService
     */
    protected \$tdbmService;
    
    /**
     * The default sort column.
     *
     * @var string
     */
    private \$defaultSort = ".($defaultSort ? "'$defaultSort'" : 'null').";
    
    /**
     * The default sort direction.
     *
     * @var string
     */
    private \$defaultDirection = ".($defaultSort && $defaultSortDirection ? "'$defaultSortDirection'" : "'asc'").";
    
    /**
     * Sets the TDBM service used by this DAO.
     *
     * @param TDBMService \$tdbmService
     */
    public function __construct(TDBMService \$tdbmService)
    {
        \$this->tdbmService = \$tdbmService;
    }

    /**
     * Return a new instance of $beanClassWithoutNameSpace object, that will be persisted in database.
     *
     * @return $beanClassWithoutNameSpace
     */// TODO!
    /*public function create()
    {
        return \$this->tdbmService->getNewObject('$tableName', true);
    }*/
    
    /**
     * Persist the $beanClassWithoutNameSpace instance.
     *
     * @param $beanClassWithoutNameSpace \$obj The bean to save.
     */
    public function save($beanClassWithoutNameSpace \$obj)
    {
        \$obj->save();
    }

    /**
     * Get all $tableCamel records. 
     *
     * @return {$beanClassWithoutNameSpace}[]|ResultIterator|ResultArray
     */
    public function getList()
    {
        if (\$this->defaultSort) {
            \$orderBy = '$tableName.'.\$this->defaultSort.' '.\$this->defaultDirection;
        } else {
            \$orderBy = null;
        }
        return \$this->tdbmService->getObjects('$tableName',  null, [], \$orderBy);
    }
    ";

        if (count($primaryKeyColumns) === 1) {
            $primaryKeyColumn = $primaryKeyColumns[0];
            $str .= "
    /**
     * Get $beanClassWithoutNameSpace specified by its ID (its primary key)
     * If the primary key does not exist, an exception is thrown.
     *
     * @param string|int \$id
     * @param bool \$lazyLoading If set to true, the object will not be loaded right away. Instead, it will be loaded when you first try to access a method of the object.
     * @return $beanClassWithoutNameSpace
     * @throws TDBMException
     */
    public function getById(\$id, \$lazyLoading = false)
    {
        return \$this->tdbmService->findObjectByPk('$tableName', ['$primaryKeyColumn' => \$id], [], \$lazyLoading);
    }
    ";
        }
        $str .= "
    /**
     * Deletes the $beanClassWithoutNameSpace passed in parameter.
     *
     * @param $beanClassWithoutNameSpace \$obj object to delete
     * @param bool \$cascade if true, it will delete all object linked to \$obj
     */
    public function delete(\$obj, \$cascade = false)
    {
        if (\$cascade === true)
            \$this->tdbmService->deleteObject(\$obj);
        else
            \$this->tdbmService->deleteCascade(\$obj);
    }


    /**
     * Get a list of $beanClassWithoutNameSpace specified by its filters.
     *
     * @param mixed \$filterBag The filter bag (see TDBMService::getObjects for complete description)
     * @param mixed \$orderbyBag The order bag (see TDBMService::getObjects for complete description)
     * @param integer \$from The offset
     * @param integer \$limit The maximum number of rows returned
     * @return {$beanClassWithoutNameSpace}[]|ResultIterator|ResultArray
     */
    protected function getListByFilter(\$filterBag=null, \$orderbyBag=null, \$from=null, \$limit=null)
    {
        if (\$this->defaultSort && \$orderbyBag == null) {
            \$orderbyBag = new OrderByColumn('$tableName', \$this->defaultSort, \$this->defaultDirection);
        }
        return \$this->tdbmService->getObjects('$tableName', \$filterBag, \$orderbyBag, \$from, \$limit);
    }

    /**
     * Get a single $beanClassWithoutNameSpace specified by its filters.
     *
     * @param mixed \$filterBag The filter bag (see TDBMService::getObjects for complete description)
     * @return $beanClassWithoutNameSpace
     */
    protected function getByFilter(\$filterBag = null)
    {
        return \$this->tdbmService->getObject('$tableName', \$filterBag);
    }
    
    /**
     * Sets the default column for default sorting.
     *
     * @param \$defaultSort
     */
    public function setDefaultSort(\$defaultSort)
    {
        \$this->defaultSort = \$defaultSort;
    }
    ";

$str .= "
}
";

        $possibleBaseFileNames = $classNameMapper->getPossibleFileNames($daonamespace."\\".$baseClassName);
        if (!$possibleBaseFileNames) {
            // @codeCoverageIgnoreStart
            throw new TDBMException('Sorry, autoload namespace issue. The class "'.$baseClassName.'" is not autoloadable.');
            // @codeCoverageIgnoreEnd
        }
        $possibleBaseFileName = $this->rootPath.$possibleBaseFileNames[0];

        $this->ensureDirectoryExist($possibleBaseFileName);
        file_put_contents($possibleBaseFileName ,$str);
        @chmod($possibleBaseFileName, 0664);

        $possibleFileNames = $classNameMapper->getPossibleFileNames($daonamespace."\\".$className);
        if (!$possibleFileNames) {
            // @codeCoverageIgnoreStart
            throw new TDBMException('Sorry, autoload namespace issue. The class "'.$className.'" is not autoloadable.');
            // @codeCoverageIgnoreEnd
        }
        $possibleFileName = $this->rootPath.$possibleFileNames[0];
        
        // Now, let's generate the "editable" class
        if (!file_exists($possibleFileName)) {
            $str = "<?php

/*
 * This file has been automatically generated by TDBM.
 * You can edit this file as it will not be overwritten.
 */

namespace {$daonamespace};

/**
 * The $className class will maintain the persistence of $beanClassWithoutNameSpace class into the $tableName table.
 */
class $className extends $baseClassName
{

}
";
            $this->ensureDirectoryExist($possibleFileName);
            file_put_contents($possibleFileName ,$str);
            @chmod($possibleFileName, 0664);
        }
    }
    
    /**
     * Generates the factory bean.
     * 
     * @param Table[] $tableList
     */
    private function generateFactory(array $tableList, $daoFactoryClassName, $daoNamespace, ClassNameMapper $classNameMapper) {
        // For each table, let's write a property.
        
        $str = "<?php

/*
 * This file has been automatically generated by TDBM.
 * DO NOT edit this file, as it might be overwritten.
 */

namespace {$daoNamespace};
        
/**
 * The $daoFactoryClassName provides an easy access to all DAOs generated by TDBM.
 *
 */
class $daoFactoryClassName 
{
";

        foreach ($tableList as $table) {
            $tableName = $table->getName();
            $daoClassName = $this->getDaoNameFromTableName($tableName);
            $daoInstanceName = self::toVariableName($daoClassName);
            
            $str .= '    /**
     * @var '.$daoClassName.'
     */
    private $'.$daoInstanceName.';

    /**
     * Returns an instance of the '.$daoClassName.' class.
     * 
     * @return '.$daoClassName.'
     */
    public function get'.$daoClassName.'()
    {
        return $this->'.$daoInstanceName.';
    }
    
    /**
     * Sets the instance of the '.$daoClassName.' class that will be returned by the factory getter.
     *
     * @param '.$daoClassName.' $'.$daoInstanceName.'
     */
    public function set'.$daoClassName.'('.$daoClassName.' $'.$daoInstanceName.') {
        $this->'.$daoInstanceName.' = $'.$daoInstanceName.';
    }
    
';
        }
        
        
        $str .= '
}
';

        $possibleFileNames = $classNameMapper->getPossibleFileNames($daoNamespace."\\".$daoFactoryClassName);
        if (!$possibleFileNames) {
            throw new TDBMException('Sorry, autoload namespace issue. The class "'.$daoNamespace."\\".$daoFactoryClassName.'" is not autoloadable.');
        }
        $possibleFileName = $this->rootPath.$possibleFileNames[0];

        $this->ensureDirectoryExist($possibleFileName);
        file_put_contents($possibleFileName ,$str);
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
	 * @param ForeignKeyConstraint $foreignKey
	 * @param array $methodsList
	 * @param string $type "get" or "set"
	 * @return string
	 */
    public static function getMethodNameForConstrainedObject(ForeignKeyConstraint $foreignKey, array $methodsList, $type) {
		// First, are there many column or only one?
		// If one column, we name the setter after it. Otherwise, we name the setter after the table name
		if (count($foreignKey->getLocalColumns()) > 1) {
			$method = $type.self::toSingular(self::toCamelCase($foreignKey->getForeignTableName()));
			if (isset($methodsList[$method])) {
				$method .= 'By'.implode('And', $foreignKey->getLocalColumns());
			}
			if (isset($methodsList[$method])) {
				throw new TDBMException("Unsolvable name conflict while generating method name");
			}
		} else {
			$column = $foreignKey->getLocalColumns()[0];
			// Let's remove any _id or id_.
			if (strpos(strtolower($column), "id_") === 0) {
				$column = substr($column, 3);
			}
			if (strrpos(strtolower($column), "_id") === strlen($column)-3) {
				$column = substr($column, 0, strlen($column)-3);
			}
			$method = $type.self::toCamelCase($column);
			if (isset($methodsList[$method])) {
				$method .= 'Object';
			}
			if (isset($methodsList[$method])) {
				throw new TDBMException("Unsolvable name conflict while generating method name");
			}
		}
        return $method;
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
            if (strpos($str, "_") === false && strpos($str, " ") === false) {
                break;
			}
                
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
     * We assume the table names are in english.
     *
     * @param $str string
     * @return string
     */
    public static function toSingular($str) {
        return \ICanBoogie\singularize($str, "en");
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

    /**
     * Ensures the file passed in parameter can be written in its directory.
     * @param string $fileName
     */
    private function ensureDirectoryExist($fileName) {
        $dirName = dirname($fileName);
        if (!file_exists($dirName)) {
            $old = umask(0);
            $result = mkdir($dirName, 0775, true);
            umask($old);
            if ($result == false) {
                echo "Unable to create directory: ".$dirName.".";
                exit;
            }
        }
    }

    /**
     * @param string $rootPath
     */
    public function setRootPath($rootPath)
    {
        $this->rootPath = $rootPath;
    }

    /**
     * Transforms a DBAL type into a PHP type (for PHPDoc purpose)
     *
     * @param Type $type The DBAL type
     * @return string The PHP type
     */
    private function dbalTypeToPhpType(Type $type) {
        $map = [
            Type::TARRAY => 'array',
            Type::SIMPLE_ARRAY => 'array',
            Type::JSON_ARRAY => 'array',
            Type::BIGINT => 'string',
            Type::BOOLEAN => 'bool',
            Type::DATETIME => '\DateTimeInterface',
            Type::DATETIMETZ => '\DateTimeInterface',
            Type::DATE => '\DateTimeInterface',
            Type::TIME => '\DateTimeInterface',
            Type::DECIMAL => 'float',
            Type::INTEGER => 'int',
            Type::OBJECT => 'string',
            Type::SMALLINT => 'int',
            Type::STRING => 'string',
            Type::TEXT => 'string',
            Type::BINARY => 'string',
            Type::BLOB => 'string',
            Type::FLOAT => 'float',
            Type::GUID => 'string'
        ];

        return isset($map[$type->getName()])?$map[$type->getName()]:$type->getName();
    }

	/**
	 * Returns the list of columns that are not part of a foreign key.
	 *
	 * @param Table $table
	 */
	private function getColumnsOutOfForeignKey(Table $table) {
		$columns = $table->getColumns();
		$foreignKeys = $table->getForeignKeys();
		$foreignKeyColumns = [];
		foreach ($foreignKeys as $foreignKey) {
			$foreignKeyColumns = array_merge($foreignKeyColumns, $foreignKey->getLocalColumns());
		}

		return array_filter($columns, function(Column $column) use ($foreignKeyColumns) {
			return array_search($column->getName(), $foreignKeyColumns) === false;
		});
	}

    /**
     *
     * @param string $beanNamespace
     * @return \string[] Returns a map mapping table name to beans name
     */
    public function buildTableToBeanMap($beanNamespace) {
        $tableToBeanMap = [];

        $tables = $this->schema->getTables();

        foreach ($tables as $table) {
            $tableName = $table->getName();
            $tableToBeanMap[$tableName] = $beanNamespace . "\\" . self::getBeanNameFromTableName($tableName);
        }
        return $tableToBeanMap;
    }
}
