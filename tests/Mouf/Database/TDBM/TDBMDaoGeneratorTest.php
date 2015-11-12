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

use Doctrine\Common\Cache\ArrayCache;
use Mouf\Database\DBConnection\MySqlConnection;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use Mouf\Database\TDBM\Test\Dao\Bean\CountryBean;
use Mouf\Database\TDBM\Test\Dao\Bean\UserBean;
use Mouf\Database\TDBM\Test\Dao\ContactDao;
use Mouf\Database\TDBM\Test\Dao\CountryDao;
use Mouf\Database\TDBM\Test\Dao\RoleDao;
use Mouf\Database\TDBM\Test\Dao\UserDao;
use Mouf\Database\TDBM\Utils\TDBMDaoGenerator;
use Mouf\Utils\Cache\NoCache;


/**
 */
class TDBMDaoGeneratorTest extends TDBMAbstractServiceTest {

    /** @var TDBMDaoGenerator $tdbmDaoGenerator */
    protected $tdbmDaoGenerator;

    private $rootPath;

    protected function setUp() {
        parent::setUp();
        $schemaManager = $this->tdbmService->getConnection()->getSchemaManager();
        $schemaAnalyzer = new SchemaAnalyzer($schemaManager);
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($this->tdbmService->getConnection(), new ArrayCache(), $schemaAnalyzer);
        $this->tdbmDaoGenerator = new TDBMDaoGenerator($schemaAnalyzer, $schemaManager->createSchema(), $tdbmSchemaAnalyzer);
        $this->rootPath = __DIR__.'/../../../../';
        $this->tdbmDaoGenerator->setRootPath($this->rootPath);

        $beannamespace = "Mouf\\Database\\TDBM\\Test\\Dao\\Bean";
        $tableToBeanMap = $this->tdbmDaoGenerator->buildTableToBeanMap($beannamespace);
        $this->tdbmService->setTableToBeanMap($tableToBeanMap);
    }

	public function testDaoGeneration() {
        $daoFactoryClassName = "DaoFactory";
        $daonamespace = "Mouf\\Database\\TDBM\\Test\\Dao";
        $beannamespace = "Mouf\\Database\\TDBM\\Test\\Dao\\Bean";
        $storeInUtc = false;

        // Remove all previously generated files.
        $this->recursiveDelete($this->rootPath."src/Mouf/Database/TDBM/Test/Dao/");

        $tables = $this->tdbmDaoGenerator->generateAllDaosAndBeans($daoFactoryClassName, $daonamespace, $beannamespace, $storeInUtc);

        // Let's require all files to check they are valid PHP!
        // Test the daoFactory
        require_once($this->rootPath.'src/Mouf/Database/TDBM/Test/Dao/DaoFactory.php');
        // Test the others
        foreach ($tables as $table) {
            $daoName = $this->tdbmDaoGenerator->getDaoNameFromTableName($table);
            $daoBaseName = $this->tdbmDaoGenerator->getBaseDaoNameFromTableName($table);
            $beanName = $this->tdbmDaoGenerator->getBeanNameFromTableName($table);
            $baseBeanName = $this->tdbmDaoGenerator->getBaseBeanNameFromTableName($table);
            require_once($this->rootPath.'src/Mouf/Database/TDBM/Test/Dao/Bean/'.$baseBeanName.".php");
            require_once($this->rootPath.'src/Mouf/Database/TDBM/Test/Dao/Bean/'.$beanName.".php");
            require_once($this->rootPath.'src/Mouf/Database/TDBM/Test/Dao/'.$daoBaseName.".php");
            require_once($this->rootPath.'src/Mouf/Database/TDBM/Test/Dao/'.$daoName.".php");
        }

    }

    /**
     * @expectedException \Mouf\Database\TDBM\TDBMException
     */
    public function testGenerationException() {
        $daoFactoryClassName = "DaoFactory";
        $daonamespace = "UnknownVendor\\Dao";
        $beannamespace = "UnknownVendor\\Bean";
        $storeInUtc = false;

        $this->tdbmDaoGenerator->generateAllDaosAndBeans($daoFactoryClassName, $daonamespace, $beannamespace, $storeInUtc);
    }

    /**
     * Delete a file or recursively delete a directory
     *
     * @param string $str Path to file or directory
     */
    private function recursiveDelete($str) {
        if (is_file($str)) {
            return @unlink($str);
        }
        elseif (is_dir($str)) {
            $scan = glob(rtrim($str,'/').'/*');
            foreach($scan as $index=>$path) {
                $this->recursiveDelete($path);
            }
            return @rmdir($str);
        }
    }

    public function testGeneratedGetById() {
        $contactDao = new ContactDao($this->tdbmService);
        $contactBean = $contactDao->getById(1);
        $this->assertEquals(1, $contactBean->getId());
        $this->assertInstanceOf('\\DateTimeInterface', $contactBean->getCreatedAt());

        // FIXME: Question: que faire du paramètre stockage "UTC"????
    }

    public function testForeignKeyInBean() {
        $userDao = new UserDao($this->tdbmService);
        $userBean = $userDao->getById(1);
        $country = $userBean->getCountry();

        $this->assertEquals('UK', $country->getLabel());

        $userBean2 = $userDao->getById(1);
        $this->assertTrue($userBean === $userBean2);

        $contactDao = new ContactDao($this->tdbmService);
        $contactBean = $contactDao->getById(1);

        $this->assertTrue($userBean === $contactBean);
    }


    public function testNewBeans() {
        $countryDao = new CountryDao($this->tdbmService);
        $userDao = new UserDao($this->tdbmService);
        $userBean = new UserBean('John Doe', new \DateTime(), 'john@doe.com', $countryDao->getById(2), 'john.doe');

        $userDao->save($userBean);
    }

    public function testAssigningNewBeans() {
        $userDao = new UserDao($this->tdbmService);
        $countryBean = new CountryBean("Mexico");
        $userBean = new UserBean('Speedy Gonzalez', new \DateTime(), 'speedy@gonzalez.com', $countryBean, 'speedy.gonzalez');
        $this->assertEquals($countryBean, $userBean->getCountry());

        $userDao->save($userBean);
    }

    public function testDirectReversedRelationship() {
        $countryDao = new CountryDao($this->tdbmService);
        $country = $countryDao->getById(1);
        $users = $country->getUsers();

        $arr = $users->toArray();

        $this->assertCount(1, $arr);
        $this->assertInstanceOf('Mouf\\Database\\TDBM\\Test\\Dao\\Bean\\UserBean', $arr[0]);
        $this->assertEquals('jean.dupont', $arr[0]->getLogin());
    }

    public function testJointureGetters() {
        $roleDao = new RoleDao($this->tdbmService);
        $role = $roleDao->getById(1);
        $users = $role->getUsers();

        $this->assertCount(2, $users);
        $this->assertInstanceOf('Mouf\\Database\\TDBM\\Test\\Dao\\Bean\\UserBean', $users[0]);

        $rights = $role->getRights();

        $this->assertCount(2, $rights);
        $this->assertInstanceOf('Mouf\\Database\\TDBM\\Test\\Dao\\Bean\\RightBean', $rights[0]);
    }
}
