<?php
namespace Mouf\Database\TDBM\Filters;

use DateTimeInterface;
use Mouf\Database\DBConnection\ConnectionInterface;

/**
 * Utility functions for filters
 */
class FilterUtils
{
    /**
     * @param string|null|DateTimeInterface $value
     */
    public static function valueToSql($value, ConnectionInterface $dbConnection) {
        if ($value instanceof DateTimeInterface) {
            return "'".$value->format('Y-m-d H:i:s')."'";
        } else {
            return $dbConnection->quoteSmart($value);
        }
    }
}
