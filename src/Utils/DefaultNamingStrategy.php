<?php


namespace TheCodingMachine\TDBM\Utils;

use Doctrine\Common\Inflector\Inflector;

class DefaultNamingStrategy implements NamingStrategyInterface
{
    private $beanPrefix = '';
    private $beanSuffix = '';
    private $baseBeanPrefix = 'Abstract';
    private $baseBeanSuffix = '';
    private $daoPrefix = '';
    private $daoSuffix = 'Dao';
    private $baseDaoPrefix = 'Abstract';
    private $baseDaoSuffix = 'Dao';
    private $exceptions = [];

    /**
     * Sets the string prefix to any bean class name.
     *
     * @param string $beanPrefix
     */
    public function setBeanPrefix(string $beanPrefix)
    {
        $this->beanPrefix = $beanPrefix;
    }

    /**
     * Sets the string suffix to any bean class name.
     *
     * @param string $beanSuffix
     */
    public function setBeanSuffix(string $beanSuffix)
    {
        $this->beanSuffix = $beanSuffix;
    }

    /**
     * Sets the string prefix to any base bean class name.
     *
     * @param string $baseBeanPrefix
     */
    public function setBaseBeanPrefix(string $baseBeanPrefix)
    {
        $this->baseBeanPrefix = $baseBeanPrefix;
    }

    /**
     * Sets the string suffix to any base bean class name.
     *
     * @param string $baseBeanSuffix
     */
    public function setBaseBeanSuffix(string $baseBeanSuffix)
    {
        $this->baseBeanSuffix = $baseBeanSuffix;
    }

    /**
     * Sets the string prefix to any DAO class name.
     *
     * @param string $daoPrefix
     */
    public function setDaoPrefix(string $daoPrefix)
    {
        $this->daoPrefix = $daoPrefix;
    }

    /**
     * Sets the string suffix to any DAO class name.
     *
     * @param string $daoSuffix
     */
    public function setDaoSuffix(string $daoSuffix)
    {
        $this->daoSuffix = $daoSuffix;
    }

    /**
     * Sets the string prefix to any base DAO class name.
     *
     * @param string $baseDaoPrefix
     */
    public function setBaseDaoPrefix(string $baseDaoPrefix)
    {
        $this->baseDaoPrefix = $baseDaoPrefix;
    }

    /**
     * Sets the string suffix to any base DAO class name.
     *
     * @param string $baseDaoSuffix
     */
    public function setBaseDaoSuffix(string $baseDaoSuffix)
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
        return $this->beanPrefix.$this->toSingularCamelCase($tableName).$this->beanSuffix;
    }

    /**
     * Returns the base bean class name from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getBaseBeanClassName(string $tableName): string
    {
        return $this->baseBeanPrefix.$this->toSingularCamelCase($tableName).$this->baseBeanSuffix;
    }

    /**
     * Returns the name of the DAO class from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getDaoClassName(string $tableName): string
    {
        return $this->daoPrefix.$this->toSingularCamelCase($tableName).$this->daoSuffix;
    }

    /**
     * Returns the name of the base DAO class from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getBaseDaoClassName(string $tableName): string
    {
        return $this->baseDaoPrefix.$this->toSingularCamelCase($tableName).$this->baseDaoSuffix;
    }

    /**
     * Tries to put string to the singular form (if it is plural) and camel case form.
     * We assume the table names are in english.
     *
     * @param $str string
     *
     * @return string
     */
    private function toSingularCamelCase(string $str): string
    {
        // Let's first check if this is not in the exceptions directory.
        if (isset($this->exceptions[$str])) {
            return $this->exceptions[$str];
        }

        $tokens = preg_split("/[_ ]+/", $str);
        $tokens = array_map([Inflector::class, 'singularize'], $tokens);

        $str = '';
        foreach ($tokens as $token) {
            $str .= ucfirst(Inflector::singularize($token));
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
    public function setExceptions(array $exceptions)
    {
        $this->exceptions = $exceptions;
    }
}
