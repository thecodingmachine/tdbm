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

use function sort;

class SchemaLockFileDumperTest extends TDBMAbstractServiceTest
{
    /**
     * @var SchemaLockFileDumper
     */
    protected $schemaLockFileDumper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schemaLockFileDumper = new SchemaLockFileDumper(self::getConnection(), new ArrayCache(), Configuration::getDefaultLockFilePath());
    }

    public function testSchemaLock(): void
    {
        $schemaFromConnec = self::getConnection()->getSchemaManager()->createSchema();
        $tableNames = [];
        //lock file doesn't save the database name so we have to replace it manually.
        ImmutableCaster::castSchemaToImmutable($schemaFromConnec);
        foreach ($schemaFromConnec->getTableNames() as $tableName) {
            $tableNames[] = str_replace(['tdbm_testcase', 'postgres', 'tdbm'], 'public', $tableName);
        }

        $cache = new ArrayCache();
        $schemaLockFileDumper = new SchemaLockFileDumper(self::getConnection(), $cache, Configuration::getDefaultLockFilePath());

        $schemaFromAnalyser = $schemaLockFileDumper->getSchema(true);
        $schemaFromAnalyserCached = $schemaLockFileDumper->getSchema();
        sort($tableNames);
        $tablesFromAnalyser = $schemaFromAnalyser->getTableNames();
        sort($tablesFromAnalyser);
        $tablesFromAnalyserCached = $schemaFromAnalyserCached->getTableNames();
        sort($tablesFromAnalyserCached);
        $this->assertEquals($tableNames, $tablesFromAnalyser);
        $this->assertEquals($tablesFromAnalyser, $tablesFromAnalyserCached);
    }

    public function testGetSchema(): void
    {
        $cache = new ArrayCache();
        $schemaLockFileDumper1 = new SchemaLockFileDumper(self::getConnection(), $cache, Configuration::getDefaultLockFilePath());
        $schemaLockFileDumper2 = new SchemaLockFileDumper(self::getConnection(), $cache, Configuration::getDefaultLockFilePath());

        // Why don't we go in all lines of code????
        $schema1 = $schemaLockFileDumper1->getSchema();
        // One more time to go through cache!
        $schema2 = $schemaLockFileDumper2->getSchema();
        $this->assertTrue($schema1 === $schema2);
    }
}
