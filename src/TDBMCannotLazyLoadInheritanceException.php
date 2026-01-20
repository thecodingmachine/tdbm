<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM;

/**
 * Exception thrown when TDBM cannot lazy load a bean because this bean is used in inheritance.
 */
class TDBMCannotLazyLoadInheritanceException extends TDBMException
{
    /**
     * @param string[] $tables
     */
    public static function create(array $tables): TDBMCannotLazyLoadInheritanceException
    {
        return new self(sprintf('Failed to lazy load the tables (%s) as they are part of inheritance.', implode(', ', $tables)));
    }
}
