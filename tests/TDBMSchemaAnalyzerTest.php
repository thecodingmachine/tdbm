<?php

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

class TDBMSchemaAnalyzerTest extends TDBMAbstractServiceTest
{
    /**
     * @var TDBMSchemaAnalyzer
     */
    protected $tdbmSchemaAnalyzer;

    protected function setUp()
    {
        parent::setUp();
        $schemaAnalyzer = new SchemaAnalyzer($this->dbConnection->getSchemaManager(), new ArrayCache(), 'prefix_');
        $this->tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($this->dbConnection, new ArrayCache(), $schemaAnalyzer);
    }

    public function testGetSchema()
    {
        $schemaAnalyzer = new SchemaAnalyzer($this->dbConnection->getSchemaManager(), new ArrayCache(), 'prefix_');
        $cache = new ArrayCache();
        $tdbmSchemaAnalyzer1 = new TDBMSchemaAnalyzer($this->dbConnection, $cache, $schemaAnalyzer);
        $tdbmSchemaAnalyzer2 = new TDBMSchemaAnalyzer($this->dbConnection, $cache, $schemaAnalyzer);

        // Why don't we go in all lines of code????
        $schema1 = $tdbmSchemaAnalyzer1->getSchema();
        // One more time to go through cache!
        $schema2 = $tdbmSchemaAnalyzer2->getSchema();
        $this->assertTrue($schema1 === $schema2);
    }

    public function testGetIncomingForeignKeys()
    {
        $schemaAnalyzer = new SchemaAnalyzer($this->dbConnection->getSchemaManager(), new ArrayCache(), 'prefix_');
        $cache = new ArrayCache();
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($this->dbConnection, $cache, $schemaAnalyzer);

        $fks = $tdbmSchemaAnalyzer->getIncomingForeignKeys('users');
        $this->assertCount(0, $fks);
    }

    public function testGetIncomingForeignKeys2()
    {
        $schemaAnalyzer = new SchemaAnalyzer($this->dbConnection->getSchemaManager(), new ArrayCache(), 'prefix_');
        $cache = new ArrayCache();
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($this->dbConnection, $cache, $schemaAnalyzer);

        $fks = $tdbmSchemaAnalyzer->getIncomingForeignKeys('contact');
        $this->assertCount(1, $fks);
    }

    public function testGetIncomingForeignKeys3()
    {
        $schemaAnalyzer = new SchemaAnalyzer($this->dbConnection->getSchemaManager(), new ArrayCache(), 'prefix_');
        $cache = new ArrayCache();
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($this->dbConnection, $cache, $schemaAnalyzer);

        $fks = $tdbmSchemaAnalyzer->getIncomingForeignKeys('country');
        $this->assertCount(3, $fks);
        $tables = [$fks[0]->getLocalTableName(), $fks[1]->getLocalTableName(), $fks[2]->getLocalTableName()];
        $this->assertContains('users', $tables);
        $this->assertContains('all_nullable', $tables);
        $this->assertContains('boats', $tables);
    }

    public function testGetPivotTableLinkedToTable()
    {
        $schemaAnalyzer = new SchemaAnalyzer($this->dbConnection->getSchemaManager(), new ArrayCache(), 'prefix_');
        $cache = new ArrayCache();
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($this->dbConnection, $cache, $schemaAnalyzer);

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
