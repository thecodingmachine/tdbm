<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;

use function is_array;
use function is_int;

/**
 * Utility class to ease the use of DBAL types.
 */
class DbalUtils
{
    /**
     * If a parameter is an array (used in a "IN" statement), we need to tell Doctrine about it.
     * If it is an integer we have to tell DBAL (default is string)
     * @see https://www.doctrine-project.org/projects/doctrine-dbal/en/2.9/reference/data-retrieval-and-manipulation.html#list-of-parameters-conversion
     * @param array<string, mixed> $parameters
     * @return array<string, int>
     */
    public static function generateTypes(array $parameters): array
    {
        $types = [];
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $val) {
                    if (!is_int($val)) {
                        $types[$key] = ArrayParameterType::STRING;
                        continue 2;
                    }
                }
                $types[$key] = ArrayParameterType::INTEGER;
            } elseif (is_int($value)) {
                $types[$key] = ParameterType::INTEGER;
            } elseif (is_bool($value)) {
                $types[$key] = ParameterType::BOOLEAN;
            }
        }

        return $types;
    }
}
