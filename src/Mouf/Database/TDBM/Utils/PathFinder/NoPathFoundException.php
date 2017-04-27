<?php


namespace Mouf\Database\TDBM\Utils\PathFinder;
use Mouf\Database\TDBM\TDBMException;

/**
 * Exception thrown when no path can be mapped to the class name.
 */
class NoPathFoundException extends TDBMException
{
    public static function create(string $className)
    {
        return new self(sprintf('Could not find a path where class %s would be autoloadable. Maybe consider editing your composer.json autoload section accordingly.', $className));
    }
}
