<?php

namespace TheCodingMachine\TDBM;

class TDBMInvalidArgumentException extends \InvalidArgumentException
{
    /**
     * @param mixed $value
     */
    public static function badType(string $expectedType, $value, string $location): self
    {
        return new self("Invalid argument passed to '$location'. Expecting a $expectedType. Got a ".gettype($value).'.');
    }
}
