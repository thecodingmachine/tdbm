<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Psr\Container\ContainerInterface;
use TheCodingMachine\TDBM\Schema\ForeignKeys;
use TheCodingMachine\TDBM\TDBMService;
use Zend\Code\Generator\AbstractMemberGenerator;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag\VarTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Code\Generator\PropertyGenerator;
use function str_replace;
use TheCodingMachine\TDBM\ConfigurationInterface;
use TheCodingMachine\TDBM\TDBMException;
use TheCodingMachine\TDBM\TDBMSchemaAnalyzer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use function strpos;
use function substr;
use function var_export;

/**
 * This class generates automatically DAOs and Beans for TDBM.
 */
class TDBMDaoGenerator
{
    /**
     * @var TDBMSchemaAnalyzer
     */
    private $tdbmSchemaAnalyzer;

    /**
     * @var GeneratorListenerInterface
     */
    private $eventDispatcher;

    /**
     * @var NamingStrategyInterface
     */
    private $namingStrategy;
    /**
     * @var ConfigurationInterface
     */
    private $configuration;

    /**
     * Constructor.
     *
     * @param ConfigurationInterface $configuration
     * @param TDBMSchemaAnalyzer $tdbmSchemaAnalyzer
     */
    public function __construct(ConfigurationInterface $configuration, TDBMSchemaAnalyzer $tdbmSchemaAnalyzer)
    {
        $this->configuration = $configuration;
        $this->tdbmSchemaAnalyzer = $tdbmSchemaAnalyzer;
        $this->namingStrategy = $configuration->getNamingStrategy();
        $this->eventDispatcher = $configuration->getGeneratorEventDispatcher();
    }

    /**
     * Generates all the daos and beans.
     *
     * @throws TDBMException
     */
    public function generateAllDaosAndBeans(): void
    {
        $this->tdbmSchemaAnalyzer->generateLockFile();
        $schema = $this->tdbmSchemaAnalyzer->getSchema();

        // TODO: check that no class name ends with "Base". Otherwise, there will be name clash.
        $tableList = $schema->getTables();

        // Remove all beans and daos from junction tables
        $junctionTables = $this->configuration->getSchemaAnalyzer()->detectJunctionTables(true);
        $junctionTableNames = array_map(function (Table $table) {
            return $table->getName();
        }, $junctionTables);

        $tableList = array_filter($tableList, function (Table $table) use ($junctionTableNames) {
            return !in_array($table->getName(), $junctionTableNames, true);
        });

        $this->cleanUpGenerated();

        $beanDescriptors = [];

        $beanRegistry = new BeanRegistry($this->configuration, $schema, $this->tdbmSchemaAnalyzer, $this->namingStrategy);
        foreach ($tableList as $table) {
            $beanDescriptors[] = $beanRegistry->addBeanForTable($table);
        }
        foreach ($beanDescriptors as $beanDescriptor) {
            $beanDescriptor->initBeanPropertyDescriptors();
        }
        foreach ($beanDescriptors as $beanDescriptor) {
            $this->generateBean($beanDescriptor);
            $this->generateDao($beanDescriptor);
        }

        $this->generateFactory($beanDescriptors);

        // Let's call the list of listeners
        $this->eventDispatcher->onGenerate($this->configuration, $beanDescriptors);
    }

    /**
     * Removes all files from the Generated folders.
     * This is a way to ensure that when a table is deleted, the matching bean/dao are deleted.
     * Note: only abstract generated classes are deleted. We do not delete the code that might have been customized
     * by the user. The user will need to delete this code him/herself
     */
    private function cleanUpGenerated(): void
    {
        $generatedBeanDir = $this->configuration->getPathFinder()->getPath($this->configuration->getBeanNamespace().'\\Generated\\Xxx')->getPath();
        $this->deleteAllPhpFiles($generatedBeanDir);

        $generatedDaoDir = $this->configuration->getPathFinder()->getPath($this->configuration->getDaoNamespace().'\\Generated\\Xxx')->getPath();
        $this->deleteAllPhpFiles($generatedDaoDir);
    }

    private function deleteAllPhpFiles(string $directory): void
    {
        $files = glob($directory.'/*.php');
        $fileSystem = new Filesystem();
        $fileSystem->remove($files);
    }

    /**
     * Writes the PHP bean file with all getters and setters from the table passed in parameter.
     *
     * @param BeanDescriptor  $beanDescriptor
     *
     * @throws TDBMException
     */
    public function generateBean(BeanDescriptor $beanDescriptor): void
    {
        $className = $beanDescriptor->getBeanClassName();
        $baseClassName = $beanDescriptor->getBaseBeanClassName();
        $table = $beanDescriptor->getTable();
        $beannamespace = $this->configuration->getBeanNamespace();
        $file = $beanDescriptor->generatePhpCode();
        if ($file === null) {
            return;
        }

        $possibleBaseFileName = $this->configuration->getPathFinder()->getPath($beannamespace.'\\Generated\\'.$baseClassName)->getPathname();

        $fileContent = $file->generate();

        // Hard code PSR-2 fix
        $fileContent = $this->psr2Fix($fileContent);
        // Add the declare strict-types directive
        $commentEnd = strpos($fileContent, ' */') + 3;
        $fileContent = substr($fileContent, 0, $commentEnd) . "\n\ndeclare(strict_types=1);" . substr($fileContent, $commentEnd + 1);

        $this->dumpFile($possibleBaseFileName, $fileContent);

        $possibleFileName = $this->configuration->getPathFinder()->getPath($beannamespace.'\\'.$className)->getPathname();

        if (!file_exists($possibleFileName)) {
            $tableName = $table->getName();
            $str = "<?php
/*
 * This file has been automatically generated by TDBM.
 * You can edit this file as it will not be overwritten.
 */

declare(strict_types=1);

namespace {$beannamespace};

use {$beannamespace}\\Generated\\{$baseClassName};

/**
 * The $className class maps the '$tableName' table in database.
 */
class $className extends $baseClassName
{
}
";

            $this->dumpFile($possibleFileName, $str);
        }
    }

    /**
     * Writes the PHP bean DAO with simple functions to create/get/save objects.
     *
     * @param BeanDescriptor  $beanDescriptor
     *
     * @throws TDBMException
     */
    private function generateDao(BeanDescriptor $beanDescriptor): void
    {
        $className = $beanDescriptor->getDaoClassName();
        $baseClassName = $beanDescriptor->getBaseDaoClassName();
        $beanClassName = $beanDescriptor->getBeanClassName();
        $table = $beanDescriptor->getTable();
        $file = $beanDescriptor->generateDaoPhpCode();
        if ($file === null) {
            return;
        }
        $daonamespace = $this->configuration->getDaoNamespace();
        $tableName = $table->getName();

        $beanClassWithoutNameSpace = $beanClassName;

        $possibleBaseFileName = $this->configuration->getPathFinder()->getPath($daonamespace.'\\Generated\\'.$baseClassName)->getPathname();

        $fileContent = $file->generate();

        // Hard code PSR-2 fix
        $fileContent = $this->psr2Fix($fileContent);
        // Add the declare strict-types directive
        $commentEnd = strpos($fileContent, ' */') + 3;
        $fileContent = substr($fileContent, 0, $commentEnd) . "\n\ndeclare(strict_types=1);" . substr($fileContent, $commentEnd + 1);

        $this->dumpFile($possibleBaseFileName, $fileContent);


        $possibleFileName = $this->configuration->getPathFinder()->getPath($daonamespace.'\\'.$className)->getPathname();

        // Now, let's generate the "editable" class
        if (!file_exists($possibleFileName)) {
            $str = "<?php
/*
 * This file has been automatically generated by TDBM.
 * You can edit this file as it will not be overwritten.
 */

declare(strict_types=1);

namespace {$daonamespace};

use {$daonamespace}\\Generated\\{$baseClassName};

/**
 * The $className class will maintain the persistence of $beanClassWithoutNameSpace class into the $tableName table.
 */
class $className extends $baseClassName
{
}
";
            $this->dumpFile($possibleFileName, $str);
        }
    }

    /**
     * Fixes PSR-2 for files generated by Zend-Code
     */
    private function psr2Fix(string $content): string
    {
        return str_replace(
            [
                "\n\n}\n",
                "{\n\n    use",
            ],
            [
                '}',
                "{\n    use",
            ],
            $content
        );
    }

    /**
     * Generates the factory bean.
     *
     * @param BeanDescriptor[] $beanDescriptors
     * @throws TDBMException
     */
    private function generateFactory(array $beanDescriptors) : void
    {
        $daoNamespace = $this->configuration->getDaoNamespace();
        $daoFactoryClassName = $this->namingStrategy->getDaoFactoryClassName();

        $file = new FileGenerator();
        $file->setDocBlock(
            new DocBlockGenerator(
                <<<DOC
This file has been automatically generated by TDBM.
DO NOT edit this file, as it might be overwritten.
DOC
            )
        );
        $class = new ClassGenerator();
        $file->setClass($class);
        $class->setName($daoFactoryClassName);
        $file->setNamespace($daoNamespace.'\\Generated');

        $class->setDocBlock(new DocBlockGenerator("The $daoFactoryClassName provides an easy access to all DAOs generated by TDBM."));

        $containerProperty = new PropertyGenerator('container');
        $containerProperty->setVisibility(AbstractMemberGenerator::VISIBILITY_PRIVATE);
        $containerProperty->setDocBlock(new DocBlockGenerator(null, null, [new VarTag(null, ['\\'.ContainerInterface::class])]));
        $class->addPropertyFromGenerator($containerProperty);

        $constructorMethod = new MethodGenerator(
            '__construct',
            [ new ParameterGenerator('container', ContainerInterface::class) ],
            MethodGenerator::FLAG_PUBLIC,
            '$this->container = $container;'
        );
        $constructorMethod = $this->configuration->getCodeGeneratorListener()->onDaoFactoryConstructorGenerated($constructorMethod, $beanDescriptors, $this->configuration, $class);
        if ($constructorMethod !== null) {
            $class->addMethodFromGenerator($constructorMethod);
        }

        // For each table, let's write a property.
        foreach ($beanDescriptors as $beanDescriptor) {
            $daoClassName = $beanDescriptor->getDaoClassName();
            $daoInstanceName = self::toVariableName($daoClassName);

            $daoInstanceProperty = new PropertyGenerator($daoInstanceName);
            $daoInstanceProperty->setVisibility(AbstractMemberGenerator::VISIBILITY_PRIVATE);
            $daoInstanceProperty->setDocBlock(new DocBlockGenerator(null, null, [new VarTag(null, ['\\' . $daoNamespace . '\\' . $daoClassName, 'null'])]));
            $class->addPropertyFromGenerator($daoInstanceProperty);

            $fullClassNameVarExport = var_export($daoNamespace.'\\'.$daoClassName, true);
            $getterBody = <<<BODY
if (!\$this->$daoInstanceName) {
    \$this->$daoInstanceName = \$this->container->get($fullClassNameVarExport);
}

return \$this->$daoInstanceName;
BODY;

            $getterMethod = new MethodGenerator(
                'get' . $daoClassName,
                [],
                MethodGenerator::FLAG_PUBLIC,
                $getterBody
            );
            $getterMethod->setReturnType($daoNamespace.'\\'.$daoClassName);
            $getterMethod = $this->configuration->getCodeGeneratorListener()->onDaoFactoryGetterGenerated($getterMethod, $beanDescriptor, $this->configuration, $class);
            if ($getterMethod !== null) {
                $class->addMethodFromGenerator($getterMethod);
            }

            $setterMethod = new MethodGenerator(
                'set' . $daoClassName,
                [new ParameterGenerator($daoInstanceName, '\\' . $daoNamespace . '\\' . $daoClassName)],
                MethodGenerator::FLAG_PUBLIC,
                '$this->' . $daoInstanceName . ' = $' . $daoInstanceName . ';'
            );
            $setterMethod->setReturnType('void');
            $setterMethod = $this->configuration->getCodeGeneratorListener()->onDaoFactorySetterGenerated($setterMethod, $beanDescriptor, $this->configuration, $class);
            if ($setterMethod !== null) {
                $class->addMethodFromGenerator($setterMethod);
            }
        }

        $file = $this->configuration->getCodeGeneratorListener()->onDaoFactoryGenerated($file, $beanDescriptors, $this->configuration);

        if ($file !== null) {
            $possibleFileName = $this->configuration->getPathFinder()->getPath($daoNamespace.'\\Generated\\'.$daoFactoryClassName)->getPathname();

            $fileContent = $file->generate();

            // Hard code PSR-2 fix
            $fileContent = $this->psr2Fix($fileContent);
            // Add the declare strict-types directive
            $commentEnd = strpos($fileContent, ' */') + 3;
            $fileContent = substr($fileContent, 0, $commentEnd) . "\n\ndeclare(strict_types=1);" . substr($fileContent, $commentEnd + 1);

            $this->dumpFile($possibleFileName, $fileContent);
        }
    }

    /**
     * Transforms a string to camelCase (except the first letter will be uppercase too).
     * Underscores and spaces are removed and the first letter after the underscore is uppercased.
     * Quoting is removed if present.
     *
     * @param string $str
     *
     * @return string
     */
    public static function toCamelCase(string $str) : string
    {
        $str = str_replace(array('`', '"', '[', ']'), '', $str);

        $str = strtoupper(substr($str, 0, 1)).substr($str, 1);
        while (true) {
            $pos = strpos($str, '_');
            if ($pos === false) {
                $pos = strpos($str, ' ');
                if ($pos === false) {
                    break;
                }
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
     * @param string $str
     *
     * @return string
     */
    public static function toSingular(string $str): string
    {
        return Inflector::singularize($str);
    }

    /**
     * Put the first letter of the string in lower case.
     * Very useful to transform a class name into a variable name.
     *
     * @param string $str
     *
     * @return string
     */
    public static function toVariableName(string $str): string
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
    private function ensureDirectoryExist(string $fileName): void
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

    private function dumpFile(string $fileName, string $content) : void
    {
        $this->ensureDirectoryExist($fileName);
        $fileSystem = new Filesystem();
        $fileSystem->dumpFile($fileName, $content);
        @chmod($fileName, 0664);
    }

    /**
     * Transforms a DBAL type into a PHP type (for PHPDoc purpose).
     *
     * @param Type $type The DBAL type
     *
     * @return string The PHP type
     */
    public static function dbalTypeToPhpType(Type $type) : string
    {
        $map = [
            Type::TARRAY => 'array',
            Type::SIMPLE_ARRAY => 'array',
            'json' => 'array',  // 'json' is supported from Doctrine DBAL 2.6 only.
            Type::JSON_ARRAY => 'array',
            Type::BIGINT => 'string',
            Type::BOOLEAN => 'bool',
            Type::DATETIME_IMMUTABLE => '\DateTimeImmutable',
            Type::DATETIMETZ_IMMUTABLE => '\DateTimeImmutable',
            Type::DATE_IMMUTABLE => '\DateTimeImmutable',
            Type::TIME_IMMUTABLE => '\DateTimeImmutable',
            Type::DECIMAL => 'string',
            Type::INTEGER => 'int',
            Type::OBJECT => 'string',
            Type::SMALLINT => 'int',
            Type::STRING => 'string',
            Type::TEXT => 'string',
            Type::BINARY => 'resource',
            Type::BLOB => 'resource',
            Type::FLOAT => 'float',
            Type::GUID => 'string',
        ];

        return isset($map[$type->getName()]) ? $map[$type->getName()] : $type->getName();
    }

    /**
     * @param Table $table
     * @return string[]
     * @throws TDBMException
     */
    public static function getPrimaryKeyColumnsOrFail(Table $table): array
    {
        if ($table->getPrimaryKey() === null) {
            // Security check: a table MUST have a primary key
            throw new TDBMException(sprintf('Table "%s" does not have any primary key', $table->getName()));
        }
        return $table->getPrimaryKey()->getUnquotedColumns();
    }
}
