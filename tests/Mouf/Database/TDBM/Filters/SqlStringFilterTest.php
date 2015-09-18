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

namespace Mouf\Database\TDBM\Filters;

use Mouf\Database\DBConnection\MySqlConnection;
use Mouf\Database\TDBM\TDBMAbstractServiceTest;
use Mouf\Utils\Cache\NoCache;

/**
 */
class SqlStringFilterTest extends TDBMAbstractServiceTest {

    public function testToSql() {
        $sqlStringFilter = new SqlStringFilter("foo.bar = LOWER(baz.bar) AND foo.id = zap.id");

        $this->assertEquals("foo.bar = LOWER(baz.bar) AND foo.id = zap.id", $sqlStringFilter->toSql($this->tdbmService->dbConnection));
        $this->assertContains('foo', $sqlStringFilter->getUsedTables());
        $this->assertContains('baz', $sqlStringFilter->getUsedTables());
        $this->assertContains('zap', $sqlStringFilter->getUsedTables());
        $this->assertCount(3, $sqlStringFilter->getUsedTables());
    }
}
