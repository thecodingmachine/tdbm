<?php
declare(strict_types=1);


namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use TheCodingMachine\TDBM\TDBMException;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;
use TheCodingMachine\TDBM\Utils\Annotation\Bean;

class DefaultNamingStrategy extends AbstractNamingStrategy
{
    /** @var string */
    private $beanPrefix = '';
    /** @var string */
    private $beanSuffix = '';
    /** @var string */
    private $baseBeanPrefix = 'Abstract';
    /** @var string */
    private $baseBeanSuffix = '';
    /** @var string */
    private $daoPrefix = '';
    /** @var string */
    private $daoSuffix = 'Dao';
    /** @var string */
    private $baseDaoPrefix = 'Abstract';
    /** @var string */
    private $baseDaoSuffix = 'Dao';
    /** @var string */
    private $resultIteratorPrefix = '';
    /** @var string */
    private $resultIteratorSuffix = 'ResultIterator';
    /** @var string */
    private $baseResultIteratorPrefix = 'Abstract';
    /** @var string */
    private $baseResultIteratorSuffix = 'ResultIterator';
    /** @var array<string,string> */
    private $exceptions = [];
    /** @var AnnotationParser */
    private $annotationParser;
    /** @var AbstractSchemaManager */
    private $schemaManager;
    /** @var Schema */
    private $schema;
    /** @var Inflector */
    private $inflector;

    public function __construct(AnnotationParser $annotationParser, AbstractSchemaManager $schemaManager)
    {
        $this->annotationParser = $annotationParser;
        $this->schemaManager = $schemaManager;
        $this->inflector = InflectorFactory::create()->build();
    }

    /**
     * Sets the string prefix to any bean class name.
     *
     * @param string $beanPrefix
     */
    public function setBeanPrefix(string $beanPrefix): void
    {
        $this->beanPrefix = $beanPrefix;
    }

    /**
     * Sets the string suffix to any bean class name.
     *
     * @param string $beanSuffix
     */
    public function setBeanSuffix(string $beanSuffix): void
    {
        $this->beanSuffix = $beanSuffix;
    }

    /**
     * Sets the string prefix to any base bean class name.
     *
     * @param string $baseBeanPrefix
     */
    public function setBaseBeanPrefix(string $baseBeanPrefix): void
    {
        $this->baseBeanPrefix = $baseBeanPrefix;
    }

    /**
     * Sets the string suffix to any base bean class name.
     *
     * @param string $baseBeanSuffix
     */
    public function setBaseBeanSuffix(string $baseBeanSuffix): void
    {
        $this->baseBeanSuffix = $baseBeanSuffix;
    }

    /**
     * Sets the string prefix to any DAO class name.
     *
     * @param string $daoPrefix
     */
    public function setDaoPrefix(string $daoPrefix): void
    {
        $this->daoPrefix = $daoPrefix;
    }

    /**
     * Sets the string suffix to any DAO class name.
     *
     * @param string $daoSuffix
     */
    public function setDaoSuffix(string $daoSuffix): void
    {
        $this->daoSuffix = $daoSuffix;
    }

    /**
     * Sets the string prefix to any base DAO class name.
     *
     * @param string $baseDaoPrefix
     */
    public function setBaseDaoPrefix(string $baseDaoPrefix): void
    {
        $this->baseDaoPrefix = $baseDaoPrefix;
    }

    /**
     * Sets the string suffix to any base DAO class name.
     *
     * @param string $baseDaoSuffix
     */
    public function setBaseDaoSuffix(string $baseDaoSuffix): void
    {
        $this->baseDaoSuffix = $baseDaoSuffix;
    }


    /**
     * Returns the bean class name from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getBeanClassName(string $tableName): string
    {
        return $this->beanPrefix.$this->tableNameToSingularCamelCase($tableName).$this->beanSuffix;
    }

    /**
     * Returns the base bean class name from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getBaseBeanClassName(string $tableName): string
    {
        return $this->baseBeanPrefix.$this->tableNameToSingularCamelCase($tableName).$this->baseBeanSuffix;
    }

    /**
     * Returns the name of the DAO class from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getDaoClassName(string $tableName): string
    {
        return $this->daoPrefix.$this->tableNameToSingularCamelCase($tableName).$this->daoSuffix;
    }

    /**
     * Returns the name of the base DAO class from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getBaseDaoClassName(string $tableName): string
    {
        return $this->baseDaoPrefix.$this->tableNameToSingularCamelCase($tableName).$this->baseDaoSuffix;
    }

    /**
     * Returns the name of the ResultIterator class from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getResultIteratorClassName(string $tableName): string
    {
        return $this->resultIteratorPrefix.$this->tableNameToSingularCamelCase($tableName).$this->resultIteratorSuffix;
    }

    /**
     * Returns the name of the base ResultIterator class from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getBaseResultIteratorClassName(string $tableName): string
    {
        return $this->baseResultIteratorPrefix.$this->tableNameToSingularCamelCase($tableName).$this->baseResultIteratorSuffix;
    }

    private function tableNameToSingularCamelCase(string $tableName): string
    {
        // Now, let's check if we have a @Bean annotation on it.
        /** @var Bean|null $beanAnnotation */
        $beanAnnotation = $this->annotationParser->getTableAnnotations($this->getSchema()->getTable($tableName))->findAnnotation(Bean::class);
        if ($beanAnnotation !== null) {
            return $beanAnnotation->name;
        }

        return $this->toSingularCamelCase($tableName);
    }

    /**
     * Tries to put string to the singular form (if it is plural) and camel case form.
     * We assume the table names are in english.
     *
     * @param string $str
     *
     * @return string
     */
    private function toSingularCamelCase(string $str): string
    {
        // Let's first check if this is not in the exceptions directory.
        if (isset($this->exceptions[$str])) {
            return $this->exceptions[$str];
        }

        // If everything is in uppercase (Oracle), let's lowercase everything
        if (strtoupper($str) === $str) {
            $str = strtolower($str);
        }

        $tokens = preg_split("/[_ ]+/", $str);
        if ($tokens === false) {
            throw new \RuntimeException('Unexpected preg_split error'); // @codeCoverageIgnore
        }

        $str = '';
        foreach ($tokens as $token) {
            $str .= ucfirst($this->inflector->singularize($token));
        }

        return $str;
    }

    /**
     * Put string to camel case form.
     *
     * @param string $str
     *
     * @return string
     */
    private function toCamelCase(string $str): string
    {
        // Let's first check if this is not in the exceptions directory.
        if (isset($this->exceptions[$str])) {
            return $this->exceptions[$str];
        }

        // If everything is in uppercase (Oracle), let's lowercase everything
        if (strtoupper($str) === $str) {
            $str = strtolower($str);
        }

        $tokens = preg_split("/[_ ]+/", $str);
        if ($tokens === false) {
            throw new \RuntimeException('Unexpected preg_split error'); // @codeCoverageIgnore
        }

        $str = '';
        foreach ($tokens as $token) {
            $str .= ucfirst($token);
        }

        return $str;
    }

    /**
     * Returns the class name for the DAO factory.
     *
     * @return string
     */
    public function getDaoFactoryClassName(): string
    {
        return 'DaoFactory';
    }

    /**
     * Sets exceptions in the naming of classes.
     * The key is the name of the table, the value the "base" name of beans and DAOs.
     *
     * This is very useful for dealing with plural to singular translations in non english table names.
     *
     * For instance if you are dealing with a table containing horses in French ("chevaux" that has a singular "cheval"):
     *
     * [
     *     "chevaux" => "Cheval"
     * ]
     *
     * @param array<string,string> $exceptions
     */
    public function setExceptions(array $exceptions): void
    {
        $this->exceptions = $exceptions;
    }

    protected function getForeignKeyUpperCamelCaseName(ForeignKeyConstraint $foreignKey, bool $alternativeName): string
    {
        // First, are there many column or only one?
        // If one column, we name the setter after it. Otherwise, we name the setter after the table name
        if (count($foreignKey->getUnquotedLocalColumns()) > 1) {
            $name = $this->tableNameToSingularCamelCase($foreignKey->getForeignTableName());
            if ($alternativeName) {
                $camelizedColumns = array_map(['TheCodingMachine\\TDBM\\Utils\\TDBMDaoGenerator', 'toCamelCase'], $foreignKey->getUnquotedLocalColumns());

                $name .= 'By'.implode('And', $camelizedColumns);
            }
        } else {
            $column = $foreignKey->getUnquotedLocalColumns()[0];
            // Let's remove any _id or id_.
            if (strpos(strtolower($column), 'id_') === 0) {
                $column = substr($column, 3);
            }
            if (strrpos(strtolower($column), '_id') === strlen($column) - 3) {
                $column = substr($column, 0, strlen($column) - 3);
            }
            $name = $this->toCamelCase($column);
            if ($alternativeName) {
                $name .= 'Object';
            }
        }

        return $name;
    }

    protected function getScalarColumnUpperCamelCaseName(string $columnName, bool $alternativeName): string
    {
        return $this->toCamelCase($columnName);
    }

    protected function getUpperCamelCaseName(AbstractBeanPropertyDescriptor $property): string
    {
        if ($property instanceof ObjectBeanPropertyDescriptor) {
            return $this->getForeignKeyUpperCamelCaseName($property->getForeignKey(), $property->isAlternativeName());
        }
        if ($property instanceof ScalarBeanPropertyDescriptor) {
            return $this->getScalarColumnUpperCamelCaseName($property->getColumnName(), $property->isAlternativeName());
        }
        throw new TDBMException('Unexpected property type. Should be either ObjectBeanPropertyDescriptor or ScalarBeanPropertyDescriptor'); // @codeCoverageIgnore
    }

    private function getSchema(): Schema
    {
        if ($this->schema === null) {
            $this->schema = $this->schemaManager->createSchema();
        }
        return $this->schema;
    }

    public function getAutoPivotEntityName(ForeignKeyConstraint $constraint, bool $useAlternativeName): string
    {
        return $this->getForeignKeyUpperCamelCaseName($constraint, $useAlternativeName);
    }
}
