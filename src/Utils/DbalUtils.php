<?php


namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Connection;
use function is_array;
use function is_int;

/**
 * Utility class to ease the use of DBAL types.
 */
class DbalUtils
{
    /**
     * If a parameter is an array (used in a "IN" statement), we need to tell Doctrine about it.
     * @see https://www.doctrine-project.org/projects/doctrine-dbal/en/2.9/reference/data-retrieval-and-manipulation.html#list-of-parameters-conversion
     * @param array<string, mixed> $parameters
     * @return array<string, int>
     */
    public static function generateArrayTypes(array $parameters): array
    {
        $types = [];
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $val) {
                    if (!is_int($val)) {
                        $types[$key] = Connection::PARAM_STR_ARRAY;
                        continue 2;
                    }
                }
                $types[$key] = Connection::PARAM_INT_ARRAY;
            }
        }

        return $types;
    }
}
