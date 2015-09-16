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

namespace Mouf\Database\TDBM;

use Mouf\Database\DBConnection\MySqlConnection;
use Mouf\Database\TDBM\Utils\TDBMDaoGenerator;
use Mouf\Utils\Cache\NoCache;
use Mouf\Database\TDBM\Filters\EqualFilter;
use Mouf\Database\TDBM\Filters\OrderByColumn;


/**
 */
class TDBMDaoGeneratorTest extends TDBMAbsctractServiceTest {

    /** @var TDBMDaoGenerator $tdbmDaoGenerator */
    protected $tdbmDaoGenerator;

    protected function setUp() {
        parent::setUp();
        $this->tdbmDaoGenerator = new TDBMDaoGenerator($this->dbConnection);
    }

    /**
     * @depends testFake
     */
	public function testDaoGeneration() {
        $daoFactoryClassName = "daoFactory";
        $sourcedirectory = "Mouf/Database/TDBM/Dao";
        $daonamespace = "Mouf/Database/TDBM/Dao/";
        $beannamespace = "Mouf/Database/TDBM/Dao/Bean/";
        $support = false;
        $storeInUtc = false;
        $castDatesToDateTime = true;

		return $this->tdbmDaoGenerator->generateAllDaosAndBeans($daoFactoryClassName, $sourcedirectory, $daonamespace, $beannamespace, $support, $storeInUtc, $castDatesToDateTime);
    }

}

?>