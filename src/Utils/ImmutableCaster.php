<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

class ImmutableCaster
{
    public static function castSchemaToImmutable(Schema $schema): void
    {
        foreach ($schema->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                self::toImmutableType($column);
            }
        }
    }

    /**
     * Changes the type of a column to an immutable date type if the type is a date.
     * This is needed because by default, when reading a Schema, Doctrine assumes a mutable datetime.
     */
    private static function toImmutableType(Column $column): void
    {
        $mapping = [
            Types::DATE_MUTABLE => Types::DATE_IMMUTABLE,
            Types::DATETIME_MUTABLE => Types::DATETIME_IMMUTABLE,
            Types::DATETIMETZ_MUTABLE => Types::DATETIMETZ_IMMUTABLE,
            Types::TIME_MUTABLE => Types::TIME_IMMUTABLE
        ];

        $typeName = $column->getType()->getName();
        if (isset($mapping[$typeName])) {
            $column->setType(Type::getType($mapping[$typeName]));
        }
    }
}
