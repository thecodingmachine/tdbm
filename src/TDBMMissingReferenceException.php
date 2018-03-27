<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM;

class TDBMMissingReferenceException extends TDBMException
{
    public static function referenceDeleted(string $tableName, AbstractTDBMObject $reference) : TDBMMissingReferenceException
    {
        return new self(sprintf("Unable to save object in table '%s'. Your object references an object of type '%s' that is deleted.", $tableName, get_class($reference)));
    }
}
