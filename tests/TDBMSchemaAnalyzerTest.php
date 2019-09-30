<?php
declare(strict_types=1);

/*
 Copyright (C) 2006-2014 David Négrier - THE CODING MACHINE

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace TheCodingMachine\TDBM;

use Doctrine\Common\Cache\ArrayCache;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use TheCodingMachine\TDBM\Utils\ImmutableCaster;

class TDBMSchemaAnalyzerTest extends TDBMAbstractServiceTest
{
    /**
     * @var TDBMSchemaAnalyzer
     */
    protected $tdbmSchemaAnalyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $schemaAnalyzer = new SchemaAnalyzer(self::getConnection()->getSchemaManager(), new ArrayCache(), 'prefix_');
        $this->tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer(self::getConnection(), new ArrayCache(), $schemaAnalyzer, Configuration::getDefaultLockFilePath());
    }

    public function testSchemaLock(): void
    {
        $schemaFromConnec = self::getConnection()->getSchemaManager()->createSchema();
        $tableNames = [];
        //lock file doesn't save the database name so we have to replace it manually.
        ImmutableCaster::castSchemaToImmutable($schemaFromConnec);
        foreach ($schemaFromConnec->getTableNames() as $tableName) {
            $tableNames[] = str_replace(['tdbm_testcase', 'postgres'], 'public', $tableName);
        }

        $schemaAnalyzer = new SchemaAnalyzer(self::getConnection()->getSchemaManager(), new ArrayCache(), 'prefix_');
        $cache = new ArrayCache();
        $tdbmSchemaAnalyzer1 = new TDBMSchemaAnalyzer(self::getConnection(), $cache, $schemaAnalyzer, Configuration::getDefaultLockFilePath());

        $schemaFromAnalyser = $tdbmSchemaAnalyzer1->getSchema(true);
        $schemaFromAnalyserCached = $tdbmSchemaAnalyzer1->getSchema();
        $this->assertEquals($tableNames, $schemaFromAnalyser->getTableNames());
        $this->assertEquals($schemaFromAnalyser->getTableNames(), $schemaFromAnalyserCached->getTableNames());
    }

    public function testGetSchema(): void
    {
        $schemaAnalyzer = new SchemaAnalyzer(self::getConnection()->getSchemaManager(), new ArrayCache(), 'prefix_');
        $cache = new ArrayCache();
        $tdbmSchemaAnalyzer1 = new TDBMSchemaAnalyzer(self::getConnection(), $cache, $schemaAnalyzer, Configuration::getDefaultLockFilePath());
        $tdbmSchemaAnalyzer2 = new TDBMSchemaAnalyzer(self::getConnection(), $cache, $schemaAnalyzer, Configuration::getDefaultLockFilePath());

        // Why don't we go in all lines of code????
        $schema1 = $tdbmSchemaAnalyzer1->getSchema();
        // One more time to go through cache!
        $schema2 = $tdbmSchemaAnalyzer2->getSchema();
        $this->assertTrue($schema1 === $schema2);
    }

    public function testGetIncomingForeignKeys(): void
    {
        $schemaAnalyzer = new SchemaAnalyzer(self::getConnection()->getSchemaManager(), new ArrayCache(), 'prefix_');
        $cache = new ArrayCache();
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer(self::getConnection(), $cache, $schemaAnalyzer, Configuration::getDefaultLockFilePath());

        $fks = $tdbmSchemaAnalyzer->getIncomingForeignKeys('users');
        $this->assertCount(1, $fks);
    }

    public function testGetIncomingForeignKeys2(): void
    {
        $schemaAnalyzer = new SchemaAnalyzer(self::getConnection()->getSchemaManager(), new ArrayCache(), 'prefix_');
        $cache = new ArrayCache();
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer(self::getConnection(), $cache, $schemaAnalyzer, Configuration::getDefaultLockFilePath());

        $fks = $tdbmSchemaAnalyzer->getIncomingForeignKeys('contact');
        $this->assertCount(1, $fks);
    }

    public function testGetIncomingForeignKeys3(): void
    {
        $schemaAnalyzer = new SchemaAnalyzer(self::getConnection()->getSchemaManager(), new ArrayCache(), 'prefix_');
        $cache = new ArrayCache();
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer(self::getConnection(), $cache, $schemaAnalyzer, Configuration::getDefaultLockFilePath());

        $fks = $tdbmSchemaAnalyzer->getIncomingForeignKeys('country');
        $this->assertCount(5, $fks);
        $tables = [$fks[0]->getLocalTableName(), $fks[1]->getLocalTableName(), $fks[2]->getLocalTableName(), $fks[3]->getLocalTableName(), $fks[4]->getLocalTableName()];
        $this->assertContains('users', $tables);
        $this->assertContains('all_nullable', $tables);
        $this->assertContains('boats', $tables);
        $this->assertContains('states', $tables);
    }

    public function testGetPivotTableLinkedToTable(): void
    {
        $schemaAnalyzer = new SchemaAnalyzer(self::getConnection()->getSchemaManager(), new ArrayCache(), 'prefix_');
        $cache = new ArrayCache();
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer(self::getConnection(), $cache, $schemaAnalyzer, Configuration::getDefaultLockFilePath());

        $pivotTables = $tdbmSchemaAnalyzer->getPivotTableLinkedToTable('rights');
        $this->assertCount(1, $pivotTables);
        $this->assertEquals('roles_rights', $pivotTables[0]);

        $pivotTables = $tdbmSchemaAnalyzer->getPivotTableLinkedToTable('animal');
        $this->assertCount(0, $pivotTables);

        $pivotTables = $tdbmSchemaAnalyzer->getPivotTableLinkedToTable('animal');
        $this->assertCount(0, $pivotTables);
    }

    /*public function testGetCompulsoryColumnsWithNoInheritance() {
        $table = $this->tdbmSchemaAnalyzer->getSchema()->getTable('country');
        $compulsoryColumns = $this->tdbmSchemaAnalyzer->getCompulsoryProperties($table);
        $this->assertCount(1, $compulsoryColumns);
        $this->assertArrayHasKey("label", $compulsoryColumns);
    }

    public function testGetCompulsoryColumnsWithInheritance() {
        $table = $this->tdbmSchemaAnalyzer->getSchema()->getTable('users');
        $compulsoryColumns = $this->tdbmSchemaAnalyzer->getCompulsoryProperties($table);
        $this->assertCount(5, $compulsoryColumns);
        $this->assertEquals(['name', 'created_at', 'email', 'country_id', 'login'], array_keys($compulsoryColumns));
    }*/
}
