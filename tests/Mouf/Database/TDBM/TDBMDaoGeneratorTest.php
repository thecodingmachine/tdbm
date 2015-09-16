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


/**
 */
class TDBMDaoGeneratorTest extends TDBMAbstractServiceTest {

    /** @var TDBMDaoGenerator $tdbmDaoGenerator */
    protected $tdbmDaoGenerator;

    private $rootPath = __DIR__.'/../../../../';

    protected function setUp() {
        parent::setUp();
        $this->tdbmDaoGenerator = new TDBMDaoGenerator($this->dbConnection);
        $this->tdbmDaoGenerator->setRootPath($this->rootPath);
    }

	public function testDaoGeneration() {
        $daoFactoryClassName = "DaoFactory";
        $daonamespace = "Mouf\\Database\\TDBM\\Test\\Dao";
        $beannamespace = "Mouf\\Database\\TDBM\\Test\\Dao\\Bean";
        $support = false;
        $storeInUtc = false;
        $castDatesToDateTime = true;

        $tables = $this->tdbmDaoGenerator->generateAllDaosAndBeans($daoFactoryClassName, $daonamespace, $beannamespace, $support, $storeInUtc, $castDatesToDateTime);

        // Test the daoFactory
        require_once($this->rootPath.'src/Mouf/Database/TDBM/Test/Dao/DaoFactory.php');
        // Test the others
        foreach ($tables as $table) {
            $daoName = $this->tdbmDaoGenerator->getDaoNameFromTableName($table);
            $daoBaseName = $daoName."Base";
            $beanName = $this->tdbmDaoGenerator->getBeanNameFromTableName($table);
            $baseBeanName = $this->tdbmDaoGenerator->getBaseBeanNameFromTableName($table);
            // DaoDirectory and BeanDirectory are private
            require_once($this->rootPath.'src/Mouf/Database/TDBM/Test/Dao/Bean/'.$baseBeanName.".php");
            require_once($this->rootPath.'src/Mouf/Database/TDBM/Test/Dao/Bean/'.$beanName.".php");
            require_once($this->rootPath.'src/Mouf/Database/TDBM/Test/Dao/'.$daoBaseName.".php");
            require_once($this->rootPath.'src/Mouf/Database/TDBM/Test/Dao/'.$daoName.".php");
        }
    }
}
