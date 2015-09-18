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
class InFilterTest extends TDBMAbstractServiceTest {

    public function testToSql() {
        $inFilter = new InFilter("foo", "bar", [1, null, new \DateTimeImmutable("1978-05-05 20:27:00")]);

        $this->assertEquals("foo.bar IN ('1',NULL,'1978-05-05 20:27:00')", $inFilter->toSql($this->tdbmService->dbConnection));
        $this->assertEquals(['foo'], $inFilter->getUsedTables());
    }
}
