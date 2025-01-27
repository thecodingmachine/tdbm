<?php

namespace TheCodingMachine\TDBM\SchemaVersionControl;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;

/**
 * Database schema normalizer.
 *
 * Given an instance of Schema, it will construct a deep associative array to describe it. Such an array will then be
 * easy to serialize.
 */
class SchemaNormalizer
{
    /** @var Schema */
    protected $schema;

    /**
     * Normalize a Schema object into an array descriptor
     * @param Schema $schema
     * @return array
     */
    public function normalize(Schema $schema): array
    {
        $this->schema = $schema;
        $schemaDesc = [];
        $schemaDesc['tables'] = [];
        foreach ($schema->getTables() as $table) {
            $schemaDesc['tables'][$table->getName()] = $this->normalizeTable($table);
        }
        return $schemaDesc;
    }

    protected function normalizeTable(Table $table)
    {
        $tableDesc = [];

        if ($table->hasPrimaryKey()) {
            $pk_columns = $table->getPrimaryKey()->getUnquotedColumns();
        } else {
            $pk_columns = [];
        }

        if ($table->hasOption('comment') && $table->getOption('comment')) {
            $tableDesc['comment'] = $table->getOption('comment');
        }

        // list columns
        foreach ($table->getColumns() as $columnName => $column) {
            $tableDesc['columns'][$column->getName()] = $this->normalizeColumn($column, in_array($column->getName(), $pk_columns));
        }

        // list indexes
        foreach ($table->getIndexes() as $index) {
            if (!$index->isPrimary()) {
                $tableDesc['indexes'][$index->getName()] = $this->normalizeIndex($index);
            }
        }

        // list foreign keys
        foreach ($table->getForeignKeys() as $foreignKey) {
            $tableDesc['foreign_keys'][$foreignKey->getName()] = $this->normalizeForeignKeyConstraint($foreignKey);
        }

        return $tableDesc;
    }

    protected function normalizeColumn(Column $column, bool $isPrimaryKey)
    {
        $columnDesc = [];
        if ($isPrimaryKey) {
            $columnDesc['primary_key'] = $isPrimaryKey;
        }
        $columnDesc['type'] = $column->getType()->getName();
        if ($column->getUnsigned()) {
            $columnDesc['unsigned'] = $column->getUnsigned();
        }
        if ($column->getFixed()) {
            $columnDesc['fixed'] = $column->getFixed();
        }
        if ($column->getLength() !== null) {
            $columnDesc['length'] = $column->getLength();
        }
        if ($column->getPrecision() !== 10) {
            $columnDesc['precision'] = $column->getPrecision();
        }
        if ($column->getScale() !== 0) {
            $columnDesc['scale'] = $column->getScale();
        }
        if ($column->getNotnull()) {
            $columnDesc['not_null'] = $column->getNotnull();
        }
        if ($column->getDefault() !== null) {
            $columnDesc['default'] = $column->getDefault();
        }
        if ($column->getAutoincrement()) {
            $columnDesc['auto_increment'] = $column->getAutoincrement();
        }
        if ($column->getComment() !== null) {
            $columnDesc['comment'] = $column->getComment();
        }
        if (!empty($column->getCustomSchemaOptions())) {
            $columnDesc['custom'] = $column->getCustomSchemaOptions();
        }

        if (count($columnDesc) > 1) {
            return $columnDesc;
        }

        return $columnDesc['type'];
    }

    protected function normalizeForeignKeyConstraint(ForeignKeyConstraint $foreignKeyConstraint)
    {
        $constraintDesc = [];
        if (count($foreignKeyConstraint->getColumns()) > 1) {
            $constraintDesc['columns'] = $foreignKeyConstraint->getColumns();
        } else {
            $constraintDesc['column'] = $foreignKeyConstraint->getColumns()[0];
        }

        $constraintDesc['references'] = $this->normalizeForeignReference($foreignKeyConstraint);
        if (!empty($foreignKeyConstraint->getOptions())) {
            $constraintDesc = array_merge($constraintDesc, $foreignKeyConstraint->getOptions());
        }
        return $constraintDesc;
    }

    protected function normalizeForeignReference(ForeignKeyConstraint $foreignKeyConstraint)
    {
        $referenceDesc = [];
        $foreignTableName = $foreignKeyConstraint->getForeignTableName();
        $foreignTable = $this->schema->getTable($foreignTableName);
        if ($foreignTable->hasPrimaryKey()
            && $foreignTable->getPrimaryKeyColumns() == $foreignKeyConstraint->getForeignColumns()) {
            $referenceDesc = $foreignKeyConstraint->getForeignTableName();
        } else {
            $referenceDesc['table'] = $foreignKeyConstraint->getForeignTableName();
            $fkColumns = $foreignKeyConstraint->getForeignColumns();
            if (count($fkColumns) > 1) {
                $referenceDesc['columns'] = $fkColumns;
            } else {
                $referenceDesc['column'] = $fkColumns[0];
            }
        }
        return $referenceDesc;
    }

    protected function normalizeIndex(Index $index)
    {
        $indexDesc = [];
        $columns = $index->getColumns();
        if (count($columns) > 1) {
            $indexDesc['columns'] = $index->getColumns();
        } else {
            $indexDesc['column'] = $index->getColumns()[0];
        }
        if ($index->isUnique()) {
            $indexDesc['unique'] = $index->isUnique();
        }
        if ($index->isPrimary()) {
            $indexDesc['primary'] = $index->isPrimary();
        }
        if (!empty($index->getOptions())) {
            $indexDesc = array_merge($indexDesc, $index->getOptions());
        }
        return $indexDesc;
    }
}
