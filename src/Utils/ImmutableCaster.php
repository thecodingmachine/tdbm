<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;

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
            Type::DATE => Type::DATE_IMMUTABLE,
            Type::DATETIME => Type::DATETIME_IMMUTABLE,
            Type::DATETIMETZ => Type::DATETIMETZ_IMMUTABLE,
            Type::TIME => Type::TIME_IMMUTABLE
        ];

        $typeName = $column->getType()->getName();
        if (isset($mapping[$typeName])) {
            $column->setType(Type::getType($mapping[$typeName]));
        }
    }
}
