<?php
namespace Mouf\Database\TDBM\Filters;

use DateTimeInterface;
use Doctrine\DBAL\Driver\Connection;

/**
 * Utility functions for filters
 */
class FilterUtils
{
    /**
     * @param string|null|DateTimeInterface $value
     */
    public static function valueToSql($value, Connection $dbConnection) {
        if ($value instanceof DateTimeInterface) {
            return "'".$value->format('Y-m-d H:i:s')."'";
        } else {
            return $dbConnection->quote($value);
        }
    }
}
