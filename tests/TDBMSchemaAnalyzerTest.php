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
        $schemaLockFileDumper = new SchemaLockFileDumper(self::getConnection(), new ArrayCache(), Configuration::getDefaultLockFilePath());
        $this->tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer(self::getConnection(), new ArrayCache(), $schemaAnalyzer, $schemaLockFileDumper);
    }

    public function testGetIncomingForeignKeys(): void
    {
        $fks = $this->tdbmSchemaAnalyzer->getIncomingForeignKeys('users');
        $this->assertCount(1, $fks);
    }

    public function testGetIncomingForeignKeys2(): void
    {
        $fks = $this->tdbmSchemaAnalyzer->getIncomingForeignKeys('contact');
        $this->assertCount(1, $fks);
    }

    public function testGetIncomingForeignKeys3(): void
    {
        $fks = $this->tdbmSchemaAnalyzer->getIncomingForeignKeys('country');
        $this->assertCount(5, $fks);
        $tables = [$fks[0]->getLocalTableName(), $fks[1]->getLocalTableName(), $fks[2]->getLocalTableName(), $fks[3]->getLocalTableName(), $fks[4]->getLocalTableName()];
        $this->assertContains('users', $tables);
        $this->assertContains('all_nullable', $tables);
        $this->assertContains('boats', $tables);
        $this->assertContains('states', $tables);
    }

    public function testGetPivotTableLinkedToTable(): void
    {
        $pivotTables = $this->tdbmSchemaAnalyzer->getPivotTableLinkedToTable('rights');
        $this->assertCount(1, $pivotTables);
        $this->assertEquals('roles_rights', $pivotTables[0]);

        $pivotTables = $this->tdbmSchemaAnalyzer->getPivotTableLinkedToTable('animal');
        $this->assertCount(0, $pivotTables);

        $pivotTables = $this->tdbmSchemaAnalyzer->getPivotTableLinkedToTable('animal');
        $this->assertCount(0, $pivotTables);
    }
}
