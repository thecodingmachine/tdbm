<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\SchemaVersionControl;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use PHPUnit\Framework\TestCase;

class SchemaNormalizerTest extends TestCase
{
    public function testForeignKeysAreSortedAlphabetically(): void
    {
        $schema = new Schema();
        $users = $schema->createTable('users');
        $users->addColumn('id', 'integer');
        $users->setPrimaryKey(['id']);

        $reviews = $schema->createTable('reviews');
        $reviews->addColumn('id', 'integer');
        $reviews->addColumn('reviewed_by', 'integer');
        $reviews->addColumn('reversed_by', 'integer');
        $reviews->setPrimaryKey(['id']);
        // Add in Z→A order to prove sorting is applied, not insertion order
        $reviews->addForeignKeyConstraint('users', ['reversed_by'], ['id'], [], 'FK_ZZZ');
        $reviews->addForeignKeyConstraint('users', ['reviewed_by'], ['id'], [], 'FK_AAA');

        $normalizer = new SchemaNormalizer();
        $result = $normalizer->normalize($schema, new SchemaConfig());

        $fkKeys = array_keys($result['tables']['reviews']['foreign_keys']);
        $this->assertSame(['FK_AAA', 'FK_ZZZ'], $fkKeys);
    }
}
