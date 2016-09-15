<?php

namespace Mouf\Database\TDBM\Utils;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Mouf\Composer\ClassNameMapper;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use Mouf\Database\TDBM\TDBMException;
use Mouf\Database\TDBM\TDBMSchemaAnalyzer;

/**
 * This class generates automatically DAOs and Beans for TDBM.
 */
class TDBMDaoGenerator
{
    /**
     * @var SchemaAnalyzer
     */
    private $schemaAnalyzer;

    /**
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
     * Name of composer file.
     *
     * @var string
     */
    private $composerFile;

    /**
     * @var TDBMSchemaAnalyzer
     */
    private $tdbmSchemaAnalyzer;

    /**
     * Constructor.
     *
     * @param SchemaAnalyzer     $schemaAnalyzer
     * @param Schema             $schema
     * @param TDBMSchemaAnalyzer $tdbmSchemaAnalyzer
     */
    public function __construct(SchemaAnalyzer $schemaAnalyzer, Schema $schema, TDBMSchemaAnalyzer $tdbmSchemaAnalyzer)
    {
        $this->schemaAnalyzer = $schemaAnalyzer;
        $this->schema = $schema;
        $this->tdbmSchemaAnalyzer = $tdbmSchemaAnalyzer;
        $this->rootPath = __DIR__.'/../../../../../../../../';
        $this->composerFile = 'composer.json';
    }

    /**
     * Generates all the daos and beans.
     *
     * @param string $daoFactoryClassName The classe name of the DAO factory
     * @param string $daonamespace        The namespace for the DAOs, without trailing \
     * @param string $beannamespace       The Namespace for the beans, without trailing \
     * @param bool   $storeInUtc          If the generated daos should store the date in UTC timezone instead of user's timezone
     *
     * @return \string[] the list of tables
     *
     * @throws TDBMException
     */
    public function generateAllDaosAndBeans($daoFactoryClassName, $daonamespace, $beannamespace, $storeInUtc)
    {
        $classNameMapper = ClassNameMapper::createFromComposerFile($this->rootPath.$this->composerFile);
        // TODO: check that no class name ends with "Base". Otherwise, there will be name clash.

        $tableList = $this->schema->getTables();

        // Remove all beans and daos from junction tables
        $junctionTables = $this->schemaAnalyzer->detectJunctionTables(true);
        $junctionTableNames = array_map(function (Table $table) {
            return $table->getName();
        }, $junctionTables);

        $tableList = array_filter($tableList, function (Table $table) use ($junctionTableNames) {
            return !in_array($table->getName(), $junctionTableNames);
        });

        foreach ($tableList as $table) {
            $this->generateDaoAndBean($table, $daonamespace, $beannamespace, $classNameMapper, $storeInUtc);
        }

        $this->generateFactory($tableList, $daoFactoryClassName, $daonamespace, $classNameMapper);

        // Ok, let's return the list of all tables.
        // These will be used by the calling script to create Mouf instances.

        return array_map(function (Table $table) {
            return $table->getName();
        }, $tableList);
    }

    /**
     * Generates in one method call the daos and the beans for one table.
     *
     * @param Table           $table
     * @param string          $daonamespace
     * @param string          $beannamespace
     * @param ClassNameMapper $classNameMapper
     * @param bool            $storeInUtc
     *
     * @throws TDBMException
     */
    public function generateDaoAndBean(Table $table, $daonamespace, $beannamespace, ClassNameMapper $classNameMapper, $storeInUtc)
    {
        $tableName = $table->getName();
        $daoName = $this->getDaoNameFromTableName($tableName);
        $beanName = $this->getBeanNameFromTableName($tableName);
        $baseBeanName = $this->getBaseBeanNameFromTableName($tableName);
        $baseDaoName = $this->getBaseDaoNameFromTableName($tableName);

        $beanDescriptor = new BeanDescriptor($table, $this->schemaAnalyzer, $this->schema, $this->tdbmSchemaAnalyzer);
        $this->generateBean($beanDescriptor, $beanName, $baseBeanName, $table, $beannamespace, $classNameMapper, $storeInUtc);
        $this->generateDao($beanDescriptor, $daoName, $baseDaoName, $beanName, $table, $daonamespace, $beannamespace, $classNameMapper);
    }

    /**
     * Returns the name of the bean class from the table name.
     *
     * @param $tableName
     *
     * @return string
     */
    public static function getBeanNameFromTableName($tableName)
    {
        return self::toSingular(self::toCamelCase($tableName)).'Bean';
    }

    /**
     * Returns the name of the DAO class from the table name.
     *
     * @param $tableName
     *
     * @return string
     */
    public static function getDaoNameFromTableName($tableName)
    {
        return self::toSingular(self::toCamelCase($tableName)).'Dao';
    }

    /**
     * Returns the name of the base bean class from the table name.
     *
     * @param $tableName
     *
     * @return string
     */
    public static function getBaseBeanNameFromTableName($tableName)
    {
        return self::toSingular(self::toCamelCase($tableName)).'BaseBean';
    }

    /**
     * Returns the name of the base DAO class from the table name.
     *
     * @param $tableName
     *
     * @return string
     */
    public static function getBaseDaoNameFromTableName($tableName)
    {
        return self::toSingular(self::toCamelCase($tableName)).'BaseDao';
    }

    /**
     * Writes the PHP bean file with all getters and setters from the table passed in parameter.
     *
     * @param BeanDescriptor  $beanDescriptor
     * @param string          $className       The name of the class
     * @param string          $baseClassName   The name of the base class which will be extended (name only, no directory)
     * @param Table           $table           The table
     * @param string          $beannamespace   The namespace of the bean
     * @param ClassNameMapper $classNameMapper
     *
     * @throws TDBMException
     */
    public function generateBean(BeanDescriptor $beanDescriptor, $className, $baseClassName, Table $table, $beannamespace, ClassNameMapper $classNameMapper, $storeInUtc)
    {
        $str = $beanDescriptor->generatePhpCode($beannamespace);

        $possibleBaseFileNames = $classNameMapper->getPossibleFileNames($beannamespace.'\\Generated\\'.$baseClassName);
        if (empty($possibleBaseFileNames)) {
            throw new TDBMException('Sorry, autoload namespace issue. The class "'.$beannamespace.'\\'.$baseClassName.'" is not autoloadable.');
        }
        $possibleBaseFileName = $this->rootPath.$possibleBaseFileNames[0];

        $this->ensureDirectoryExist($possibleBaseFileName);
        file_put_contents($possibleBaseFileName, $str);
        @chmod($possibleBaseFileName, 0664);

        $possibleFileNames = $classNameMapper->getPossibleFileNames($beannamespace.'\\'.$className);
        if (empty($possibleFileNames)) {
            // @codeCoverageIgnoreStart
            throw new TDBMException('Sorry, autoload namespace issue. The class "'.$beannamespace.'\\'.$className.'" is not autoloadable.');
            // @codeCoverageIgnoreEnd
        }
        $possibleFileName = $this->rootPath.$possibleFileNames[0];
        if (!file_exists($possibleFileName)) {
            $tableName = $table->getName();
            $str = "<?php
/*
 * This file has been automatically generated by TDBM.
 * You can edit this file as it will not be overwritten.
 */

namespace {$beannamespace};

use {$beannamespace}\\Generated\\{$baseClassName};

/**
 * The $className class maps the '$tableName' table in database.
 */
class $className extends $baseClassName
{

}";
            $this->ensureDirectoryExist($possibleFileName);
            file_put_contents($possibleFileName, $str);
            @chmod($possibleFileName, 0664);
        }
    }

    /**
     * Tries to find a @defaultSort annotation in one of the columns.
     *
     * @param Table $table
     *
     * @return array First item: column name, Second item: column order (asc/desc)
     */
    private function getDefaultSortColumnFromAnnotation(Table $table)
    {
        $defaultSort = null;
        $defaultSortDirection = null;
        foreach ($table->getColumns() as $column) {
            $comments = $column->getComment();
            $matches = [];
            if (preg_match('/@defaultSort(\((desc|asc)\))*/', $comments, $matches) != 0) {
                $defaultSort = $column->getName();
                if (count($matches) === 3) {
                    $defaultSortDirection = $matches[2];
                } else {
                    $defaultSortDirection = 'ASC';
                }
            }
        }

        return [$defaultSort, $defaultSortDirection];
    }

    /**
     * Writes the PHP bean DAO with simple functions to create/get/save objects.
     *
     * @param BeanDescriptor  $beanDescriptor
     * @param string          $className       The name of the class
     * @param string          $baseClassName
     * @param string          $beanClassName
     * @param Table           $table
     * @param string          $daonamespace
     * @param string          $beannamespace
     * @param ClassNameMapper $classNameMapper
     *
     * @throws TDBMException
     */
    public function generateDao(BeanDescriptor $beanDescriptor, $className, $baseClassName, $beanClassName, Table $table, $daonamespace, $beannamespace, ClassNameMapper $classNameMapper)
    {
        $tableName = $table->getName();
        $primaryKeyColumns = $table->getPrimaryKeyColumns();

        list($defaultSort, $defaultSortDirection) = $this->getDefaultSortColumnFromAnnotation($table);

        // FIXME: lowercase tables with _ in the name should work!
        $tableCamel = self::toSingular(self::toCamelCase($tableName));

        $beanClassWithoutNameSpace = $beanClassName;
        $beanClassName = $beannamespace.'\\'.$beanClassName;

        list($usedBeans, $findByDaoCode) = $beanDescriptor->generateFindByDaoCode($beannamespace, $beanClassWithoutNameSpace);

        $usedBeans[] = $beanClassName;
        // Let's suppress duplicates in used beans (if any)
        $usedBeans = array_flip(array_flip($usedBeans));
        $useStatements = array_map(function ($usedBean) {
            return "use $usedBean;\n";
        }, $usedBeans);

        $str = "<?php

/*
 * This file has been automatically generated by TDBM.
 * DO NOT edit this file, as it might be overwritten.
 * If you need to perform changes, edit the $className class instead!
 */

namespace {$daonamespace}\\Generated;

use Mouf\\Database\\TDBM\\TDBMService;
use Mouf\\Database\\TDBM\\ResultIterator;
use Mouf\\Database\\TDBM\\ArrayIterator;
".implode('', $useStatements)."

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
    private \$defaultSort = ".($defaultSort ? "'$defaultSort'" : 'null').';

    /**
     * The default sort direction.
     *
     * @var string
     */
    private $defaultDirection = '.($defaultSort && $defaultSortDirection ? "'$defaultSortDirection'" : "'asc'").";

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
     * Persist the $beanClassWithoutNameSpace instance.
     *
     * @param $beanClassWithoutNameSpace \$obj The bean to save.
     */
    public function save($beanClassWithoutNameSpace \$obj)
    {
        \$this->tdbmService->save(\$obj);
    }

    /**
     * Get all $tableCamel records.
     *
     * @return {$beanClassWithoutNameSpace}[]|ResultIterator|ResultArray
     */
    public function findAll()
    {
        if (\$this->defaultSort) {
            \$orderBy = '$tableName.'.\$this->defaultSort.' '.\$this->defaultDirection;
        } else {
            \$orderBy = null;
        }
        return \$this->tdbmService->findObjects('$tableName',  null, [], \$orderBy);
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
    public function delete($beanClassWithoutNameSpace \$obj, \$cascade = false)
    {
        if (\$cascade === true) {
            \$this->tdbmService->deleteCascade(\$obj);
        } else {
            \$this->tdbmService->delete(\$obj);
        }
    }


    /**
     * Get a list of $beanClassWithoutNameSpace specified by its filters.
     *
     * @param mixed \$filter The filter bag (see TDBMService::findObjects for complete description)
     * @param array \$parameters The parameters associated with the filter
     * @param mixed \$orderBy The order string
     * @param array \$additionalTablesFetch A list of additional tables to fetch (for performance improvement)
     * @param int \$mode Either TDBMService::MODE_ARRAY or TDBMService::MODE_CURSOR (for large datasets). Defaults to TDBMService::MODE_ARRAY.
     * @return {$beanClassWithoutNameSpace}[]|ResultIterator|ResultArray
     */
    protected function find(\$filter = null, array \$parameters = [], \$orderBy=null, array \$additionalTablesFetch = [], \$mode = null)
    {
        if (\$this->defaultSort && \$orderBy == null) {
            \$orderBy = '$tableName.'.\$this->defaultSort.' '.\$this->defaultDirection;
        }
        return \$this->tdbmService->findObjects('$tableName', \$filter, \$parameters, \$orderBy, \$additionalTablesFetch, \$mode);
    }

    /**
     * Get a list of $beanClassWithoutNameSpace specified by its filters.
     * Unlike the `find` method that guesses the FROM part of the statement, here you can pass the \$from part.
     *
     * You should not put an alias on the main table name. So your \$from variable should look like:
     *
     *   \"$tableName JOIN ... ON ...\"
     *
     * @param string \$from The sql from statement
     * @param mixed \$filter The filter bag (see TDBMService::findObjects for complete description)
     * @param array \$parameters The parameters associated with the filter
     * @param mixed \$orderBy The order string
     * @param int \$mode Either TDBMService::MODE_ARRAY or TDBMService::MODE_CURSOR (for large datasets). Defaults to TDBMService::MODE_ARRAY.
     * @return {$beanClassWithoutNameSpace}[]|ResultIterator|ResultArray
     */
    protected function findFromSql(\$from, \$filter = null, array \$parameters = [], \$orderBy=null, \$mode = null)
    {
        if (\$this->defaultSort && \$orderBy == null) {
            \$orderBy = '$tableName.'.\$this->defaultSort.' '.\$this->defaultDirection;
        }
        return \$this->tdbmService->findObjectsFromSql('$tableName', \$from, \$filter, \$parameters, \$orderBy, \$mode);
    }

    /**
     * Get a single $beanClassWithoutNameSpace specified by its filters.
     *
     * @param mixed \$filter The filter bag (see TDBMService::findObjects for complete description)
     * @param array \$parameters The parameters associated with the filter
     * @return $beanClassWithoutNameSpace
     */
    protected function findOne(\$filter=null, array \$parameters = [])
    {
        return \$this->tdbmService->findObject('$tableName', \$filter, \$parameters);
    }

    /**
     * Get a single $beanClassWithoutNameSpace specified by its filters.
     * Unlike the `find` method that guesses the FROM part of the statement, here you can pass the \$from part.
     *
     * You should not put an alias on the main table name. So your \$from variable should look like:
     *
     *   \"$tableName JOIN ... ON ...\"
     *
     * @param string \$from The sql from statement
     * @param mixed \$filter The filter bag (see TDBMService::findObjects for complete description)
     * @param array \$parameters The parameters associated with the filter
     * @return $beanClassWithoutNameSpace
     */
    protected function findOneFromSql(\$from, \$filter=null, array \$parameters = [])
    {
        return \$this->tdbmService->findObjectFromSql('$tableName', \$from, \$filter, \$parameters);
    }

    /**
     * Sets the default column for default sorting.
     *
     * @param string \$defaultSort
     */
    public function setDefaultSort(\$defaultSort)
    {
        \$this->defaultSort = \$defaultSort;
    }
";

        $str .= $findByDaoCode;
        $str .= '}
';

        $possibleBaseFileNames = $classNameMapper->getPossibleFileNames($daonamespace.'\\Generated\\'.$baseClassName);
        if (empty($possibleBaseFileNames)) {
            // @codeCoverageIgnoreStart
            throw new TDBMException('Sorry, autoload namespace issue. The class "'.$baseClassName.'" is not autoloadable.');
            // @codeCoverageIgnoreEnd
        }
        $possibleBaseFileName = $this->rootPath.$possibleBaseFileNames[0];

        $this->ensureDirectoryExist($possibleBaseFileName);
        file_put_contents($possibleBaseFileName, $str);
        @chmod($possibleBaseFileName, 0664);

        $possibleFileNames = $classNameMapper->getPossibleFileNames($daonamespace.'\\'.$className);
        if (empty($possibleFileNames)) {
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

use {$daonamespace}\\Generated\\{$baseClassName};

/**
 * The $className class will maintain the persistence of $beanClassWithoutNameSpace class into the $tableName table.
 */
class $className extends $baseClassName
{

}
";
            $this->ensureDirectoryExist($possibleFileName);
            file_put_contents($possibleFileName, $str);
            @chmod($possibleFileName, 0664);
        }
    }

    /**
     * Generates the factory bean.
     *
     * @param Table[] $tableList
     */
    private function generateFactory(array $tableList, $daoFactoryClassName, $daoNamespace, ClassNameMapper $classNameMapper)
    {
        // For each table, let's write a property.

        $str = "<?php

/*
 * This file has been automatically generated by TDBM.
 * DO NOT edit this file, as it might be overwritten.
 */

namespace {$daoNamespace}\\Generated;
";
        foreach ($tableList as $table) {
            $tableName = $table->getName();
            $daoClassName = $this->getDaoNameFromTableName($tableName);
            $str .= "use {$daoNamespace}\\".$daoClassName.";\n";
        }

        $str .= "
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

        $possibleFileNames = $classNameMapper->getPossibleFileNames($daoNamespace.'\\Generated\\'.$daoFactoryClassName);
        if (empty($possibleFileNames)) {
            throw new TDBMException('Sorry, autoload namespace issue. The class "'.$daoNamespace.'\\'.$daoFactoryClassName.'" is not autoloadable.');
        }
        $possibleFileName = $this->rootPath.$possibleFileNames[0];

        $this->ensureDirectoryExist($possibleFileName);
        file_put_contents($possibleFileName, $str);
        @chmod($possibleFileName, 0664);
    }

    /**
     * Transforms a string to camelCase (except the first letter will be uppercase too).
     * Underscores and spaces are removed and the first letter after the underscore is uppercased.
     *
     * @param $str string
     *
     * @return string
     */
    public static function toCamelCase($str)
    {
        $str = strtoupper(substr($str, 0, 1)).substr($str, 1);
        while (true) {
            if (strpos($str, '_') === false && strpos($str, ' ') === false) {
                break;
            }

            $pos = strpos($str, '_');
            if ($pos === false) {
                $pos = strpos($str, ' ');
            }
            $before = substr($str, 0, $pos);
            $after = substr($str, $pos + 1);
            $str = $before.strtoupper(substr($after, 0, 1)).substr($after, 1);
        }

        return $str;
    }

    /**
     * Tries to put string to the singular form (if it is plural).
     * We assume the table names are in english.
     *
     * @param $str string
     *
     * @return string
     */
    public static function toSingular($str)
    {
        return Inflector::singularize($str);
    }

    /**
     * Put the first letter of the string in lower case.
     * Very useful to transform a class name into a variable name.
     *
     * @param $str string
     *
     * @return string
     */
    public static function toVariableName($str)
    {
        return strtolower(substr($str, 0, 1)).substr($str, 1);
    }

    /**
     * Ensures the file passed in parameter can be written in its directory.
     *
     * @param string $fileName
     *
     * @throws TDBMException
     */
    private function ensureDirectoryExist($fileName)
    {
        $dirName = dirname($fileName);
        if (!file_exists($dirName)) {
            $old = umask(0);
            $result = mkdir($dirName, 0775, true);
            umask($old);
            if ($result === false) {
                throw new TDBMException("Unable to create directory: '".$dirName."'.");
            }
        }
    }

    /**
     * Absolute path to composer json file.
     *
     * @param string $composerFile
     */
    public function setComposerFile($composerFile)
    {
        $this->rootPath = dirname($composerFile).'/';
        $this->composerFile = basename($composerFile);
    }

    /**
     * Transforms a DBAL type into a PHP type (for PHPDoc purpose).
     *
     * @param Type $type The DBAL type
     *
     * @return string The PHP type
     */
    public static function dbalTypeToPhpType(Type $type)
    {
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
            Type::GUID => 'string',
        ];

        return isset($map[$type->getName()]) ? $map[$type->getName()] : $type->getName();
    }

    /**
     * @param string $beanNamespace
     *
     * @return \string[] Returns a map mapping table name to beans name
     */
    public function buildTableToBeanMap($beanNamespace)
    {
        $tableToBeanMap = [];

        $tables = $this->schema->getTables();

        foreach ($tables as $table) {
            $tableName = $table->getName();
            $tableToBeanMap[$tableName] = $beanNamespace.'\\'.self::getBeanNameFromTableName($tableName);
        }

        return $tableToBeanMap;
    }
}
