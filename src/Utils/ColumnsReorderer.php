<?php


namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use ReflectionClass;
use function array_merge;
use function in_array;

/**
 * The sole purpose of this class is to work around a DBAL bug related to Oracle that
 * does not return the columns in the same order as the other databases.
 * In order to have consistent results (and consistent tests), we need those columns in
 * the same order in all databases.
 */
class ColumnsReorderer
{
    public static function reorderTableColumns(Schema $schema): void
    {
        foreach ($schema->getTables() as $table) {
            self::reorderColumnsForTable($table);
        }
    }

    private static function reorderColumnsForTable(Table $table)
    {
        $columns = self::getColumnsInExpectedOrder($table);

        // Note: the only way to rewrite columns order is to tap into a PROTECTED method.
        // This is bad BUT!
        // - we only do this for Oracle databases
        // - we know Doctrine consider protected methods as part of the API for BC changes so risk is limited.
        $refClass = new ReflectionClass(Table::class);
        $addColumnMethod = $refClass->getMethod('_addColumn');
        $addColumnMethod->setAccessible(true);

        foreach ($columns as $column) {
            $table->dropColumn($column->getName());
            $addColumnMethod->invoke($table, $column);
            //$table->_addColumn($column);
        }
    }

    /**
     * @param Table $table
     * @return Column[]
     */
    private static function getColumnsInExpectedOrder(Table $table): array {
        if ($table->hasPrimaryKey()) {
            $pkColumns = $table->getPrimaryKey()->getUnquotedColumns();
        } else {
            $pkColumns = [];
        }
        $fks = $table->getForeignKeys();

        $fkColumns = [];

        foreach ($fks as $fk) {
            $fkColumns = array_merge($fkColumns, $fk->getUnquotedLocalColumns());
        }

        $first = [];
        $second = [];
        $last = [];

        foreach ($table->getColumns() as $column) {
            if (in_array($column->getName(), $pkColumns, true)) {
                $first[] = $column;
            } elseif (in_array($column->getName(), $fkColumns, true)) {
                $second[] = $column;
            } else {
                $last[] = $column;
            }
        }
        return array_merge($first, $second, $last);
    }
}
