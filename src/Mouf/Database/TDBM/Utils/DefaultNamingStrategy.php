<?php


namespace Mouf\Database\TDBM\Utils;


use Doctrine\Common\Inflector\Inflector;

class DefaultNamingStrategy implements NamingStrategyInterface
{

    /**
     * Returns the bean class name from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getBeanClassName(string $tableName): string
    {
        return self::toSingularCamelCase($tableName).'Bean';
    }

    /**
     * Returns the base bean class name from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getBaseBeanClassName(string $tableName): string
    {
        return self::toSingularCamelCase($tableName).'BaseBean';
    }

    /**
     * Returns the name of the DAO class from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getDaoClassName(string $tableName): string
    {
        return self::toSingularCamelCase($tableName).'Dao';
    }

    /**
     * Returns the name of the base DAO class from the table name (excluding the namespace).
     *
     * @param string $tableName
     * @return string
     */
    public function getBaseDaoClassName(string $tableName): string
    {
        return self::toSingularCamelCase($tableName).'BaseDao';
    }

    /**
     * Tries to put string to the singular form (if it is plural) and camel case form.
     * We assume the table names are in english.
     *
     * @param $str string
     *
     * @return string
     */
    private static function toSingularCamelCase(string $str): string
    {
        $tokens = preg_split("/[_ ]+/", $str);
        $tokens = array_map([Inflector::class, 'singularize'], $tokens);

        $str = '';
        foreach ($tokens as $token) {
            $str .= ucfirst(Inflector::singularize($token));
        }

        return $str;
    }

    /**
     * Put the first letter of the string in lower case.
     * Very useful to transform a class name into a variable name.
     *
     * @param $str string
     *
     * @return string
     */
    private static function toVariableName($str)
    {
        return strtolower(substr($str, 0, 1)).substr($str, 1);
    }
}
