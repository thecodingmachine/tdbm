<?php

namespace Mouf\Database\TDBM;

class TDBMCyclicReferenceException extends TDBMException
{
    public static function createCyclicReference(string $tableName, AbstractTDBMObject $object) : TDBMCyclicReferenceException
    {
        return new self(sprintf("You are trying a grape of objects that reference each other. Unable to save object '%s' in table '%s'. It is already in the process of being saved.", get_class($object), $tableName));
    }

    public static function extendCyclicReference(TDBMCyclicReferenceException $e, string $tableName, AbstractTDBMObject $object, string $fkName) : TDBMCyclicReferenceException
    {
        return new self($e->getMessage().sprintf(" This object is referenced by an object of type '%s' (table '%s') via foreign key '%s'", get_class($object), $tableName, $fkName), $e->getCode(), $e);
    }
}
