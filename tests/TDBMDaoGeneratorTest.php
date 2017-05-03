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
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use TheCodingMachine\TDBM\Dao\TestRoleDao;
use TheCodingMachine\TDBM\Dao\TestUserDao;
use TheCodingMachine\TDBM\Test\Dao\AllNullableDao;
use TheCodingMachine\TDBM\Test\Dao\AnimalDao;
use TheCodingMachine\TDBM\Test\Dao\Bean\AllNullableBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\BoatBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\CatBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\CategoryBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\CountryBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\DogBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\PersonBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\RoleBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\UserBean;
use TheCodingMachine\TDBM\Test\Dao\BoatDao;
use TheCodingMachine\TDBM\Test\Dao\CatDao;
use TheCodingMachine\TDBM\Test\Dao\CategoryDao;
use TheCodingMachine\TDBM\Test\Dao\ContactDao;
use TheCodingMachine\TDBM\Test\Dao\CountryDao;
use TheCodingMachine\TDBM\Test\Dao\DogDao;
use TheCodingMachine\TDBM\Test\Dao\Generated\UserBaseDao;
use TheCodingMachine\TDBM\Test\Dao\RoleDao;
use TheCodingMachine\TDBM\Test\Dao\UserDao;
use TheCodingMachine\TDBM\Utils\PathFinder\NoPathFoundException;
use TheCodingMachine\TDBM\Utils\TDBMDaoGenerator;
use Symfony\Component\Process\Process;

class TDBMDaoGeneratorTest extends TDBMAbstractServiceTest
{
    /** @var TDBMDaoGenerator $tdbmDaoGenerator */
    protected $tdbmDaoGenerator;

    private $rootPath;

    protected function setUp()
    {
        parent::setUp();
        $schemaManager = $this->tdbmService->getConnection()->getSchemaManager();
        $schemaAnalyzer = new SchemaAnalyzer($schemaManager);
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($this->tdbmService->getConnection(), new ArrayCache(), $schemaAnalyzer);
        $this->tdbmDaoGenerator = new TDBMDaoGenerator($this->getConfiguration(), $tdbmSchemaAnalyzer);
        $this->rootPath = __DIR__.'/../';
        //$this->tdbmDaoGenerator->setComposerFile($this->rootPath.'composer.json');
    }

    public function testDaoGeneration()
    {
        // Remove all previously generated files.
        $this->recursiveDelete($this->rootPath.'src/Test/Dao/');

        $this->tdbmDaoGenerator->generateAllDaosAndBeans();

        // Let's require all files to check they are valid PHP!
        // Test the daoFactory
        require_once $this->rootPath.'src/Test/Dao/Generated/DaoFactory.php';
        // Test the others

        $beanDescriptors = $this->getDummyGeneratorListener()->getBeanDescriptors();

        foreach ($beanDescriptors as $beanDescriptor) {
            $daoName = $beanDescriptor->getDaoClassName();
            $daoBaseName = $beanDescriptor->getBaseDaoClassName();
            $beanName = $beanDescriptor->getBeanClassName();
            $baseBeanName = $beanDescriptor->getBaseBeanClassName();
            require_once $this->rootPath.'src/Test/Dao/Bean/Generated/'.$baseBeanName.'.php';
            require_once $this->rootPath.'src/Test/Dao/Bean/'.$beanName.'.php';
            require_once $this->rootPath.'src/Test/Dao/Generated/'.$daoBaseName.'.php';
            require_once $this->rootPath.'src/Test/Dao/'.$daoName.'.php';
        }

        // Check that pivot tables do not generate DAOs or beans.
        $this->assertFalse(class_exists('TheCodingMachine\\TDBM\\Test\\Dao\\RolesRightDao'));
    }

    public function testGenerationException()
    {
        $configuration = new Configuration('UnknownVendor\\Dao', 'UnknownVendor\\Bean', $this->dbConnection, $this->getNamingStrategy());

        $schemaManager = $this->tdbmService->getConnection()->getSchemaManager();
        $schemaAnalyzer = new SchemaAnalyzer($schemaManager);
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($this->tdbmService->getConnection(), new ArrayCache(), $schemaAnalyzer);
        $tdbmDaoGenerator = new TDBMDaoGenerator($configuration, $tdbmSchemaAnalyzer);
        $this->rootPath = __DIR__.'/../../../../';
        //$tdbmDaoGenerator->setComposerFile($this->rootPath.'composer.json');

        $this->expectException(NoPathFoundException::class);
        $tdbmDaoGenerator->generateAllDaosAndBeans();
    }

    /**
     * Delete a file or recursively delete a directory.
     *
     * @param string $str Path to file or directory
     * @return bool
     */
    private function recursiveDelete(string $str) : bool
    {
        if (is_file($str)) {
            return @unlink($str);
        } elseif (is_dir($str)) {
            $scan = glob(rtrim($str, '/') . '/*');
            foreach ($scan as $index => $path) {
                $this->recursiveDelete($path);
            }

            return @rmdir($str);
        }
        return false;
    }

    /**
     * @depends testDaoGeneration
     */
    public function testGetBeanClassName()
    {
        $this->assertEquals(UserBean::class, $this->tdbmService->getBeanClassName('users'));
    }

    /**
     * @depends testDaoGeneration
     */
    public function testGetBeanClassNameException()
    {
        $this->expectException(TDBMInvalidArgumentException::class);
        $this->tdbmService->getBeanClassName('not_exists');
    }

    /**
     * @depends testDaoGeneration
     */
    public function testGeneratedGetById()
    {
        $contactDao = new ContactDao($this->tdbmService);
        $contactBean = $contactDao->getById(1);
        $this->assertEquals(1, $contactBean->getId());
        $this->assertInstanceOf('\\DateTimeInterface', $contactBean->getCreatedAt());

        // FIXME: Question: que faire du paramètre stockage "UTC"????
    }

    /**
     * @depends testDaoGeneration
     */
    public function testGeneratedGetByIdLazyLoaded()
    {
        $roleDao = new RoleDao($this->tdbmService);
        $roleBean = $roleDao->getById(1, true);
        $this->assertEquals(1, $roleBean->getId());
        $this->assertInstanceOf('\\DateTimeInterface', $roleBean->getCreatedAt());

        $roleBean2 = $roleDao->getById(1, true);
        $this->assertTrue($roleBean === $roleBean2);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testDefaultValueOnNewBean()
    {
        $roleBean = new RoleBean('my_role');
        $this->assertEquals(1, $roleBean->getStatus());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testForeignKeyInBean()
    {
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

    /**
     * @depends testDaoGeneration
     */
    public function testNewBeans()
    {
        $countryDao = new CountryDao($this->tdbmService);
        $userDao = new UserDao($this->tdbmService);
        $userBean = new UserBean('John Doe', 'john@doe.com', $countryDao->getById(2), 'john.doe');
        $userBean->setOrder(1); // Let's set a "protected keyword" column.

        $userDao->save($userBean);

        $this->assertNull($userBean->getManager());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testDateTimeImmutableGetter()
    {
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);

        $this->assertInstanceOf('\DateTimeImmutable', $user->getCreatedAt());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testAssigningNewBeans()
    {
        $userDao = new UserDao($this->tdbmService);
        $countryBean = new CountryBean('Mexico');
        $userBean = new UserBean('Speedy Gonzalez', 'speedy@gonzalez.com', $countryBean, 'speedy.gonzalez');
        $this->assertEquals($countryBean, $userBean->getCountry());

        $userDao->save($userBean);
    }

    /**
     * @depends testAssigningNewBeans
     */
    public function testUpdatingProtectedColumn()
    {
        $userDao = new UserDao($this->tdbmService);
        $userBean = $userDao->findOneByLogin('speedy.gonzalez');
        $userBean->setOrder(2);
        $userDao->save($userBean);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testAssigningExistingRelationship()
    {
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);
        $countryDao = new CountryDao($this->tdbmService);
        $country = $countryDao->getById(2);

        $user->setCountry($country);
        $this->assertEquals(TDBMObjectStateEnum::STATE_DIRTY, $user->_getStatus());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testDirectReversedRelationship()
    {
        $countryDao = new CountryDao($this->tdbmService);
        $country = $countryDao->getById(1);
        $users = $country->getUsers();

        $arr = $users->toArray();

        $this->assertCount(1, $arr);
        $this->assertInstanceOf('TheCodingMachine\\TDBM\\Test\\Dao\\Bean\\UserBean', $arr[0]);
        $this->assertEquals('jean.dupont', $arr[0]->getLogin());

        $newUser = new UserBean('Speedy Gonzalez', 'speedy@gonzalez.com', $country, 'speedy.gonzalez');
        $users = $country->getUsers();

        $arr = $users->toArray();

        $this->assertCount(2, $arr);
        $this->assertInstanceOf('TheCodingMachine\\TDBM\\Test\\Dao\\Bean\\UserBean', $arr[1]);
        $this->assertEquals('speedy.gonzalez', $arr[1]->getLogin());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testDeleteInDirectReversedRelationship()
    {
        $countryDao = new CountryDao($this->tdbmService);
        $country = $countryDao->getById(1);

        $userDao = new UserDao($this->tdbmService);
        $newUser = new UserBean('John Snow', 'john@snow.com', $country, 'john.snow');
        $userDao->save($newUser);

        $users = $country->getUsers();

        $arr = $users->toArray();

        $this->assertCount(2, $arr);

        $userDao->delete($arr[1]);

        $users = $country->getUsers();
        $arr = $users->toArray();
        $this->assertCount(1, $arr);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testJointureGetters()
    {
        $roleDao = new RoleDao($this->tdbmService);
        $role = $roleDao->getById(1);
        $users = $role->getUsers();

        $this->assertCount(2, $users);
        $this->assertInstanceOf('TheCodingMachine\\TDBM\\Test\\Dao\\Bean\\UserBean', $users[0]);

        $rights = $role->getRights();

        $this->assertCount(2, $rights);
        $this->assertInstanceOf('TheCodingMachine\\TDBM\\Test\\Dao\\Bean\\RightBean', $rights[0]);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testNewBeanConstructor()
    {
        $role = new RoleBean('Newrole');
        $this->assertEquals(TDBMObjectStateEnum::STATE_DETACHED, $role->_getStatus());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testJointureAdderOnNewBean()
    {
        $countryDao = new CountryDao($this->tdbmService);
        $country = $countryDao->getById(1);
        $user = new UserBean('Speedy Gonzalez', 'speedy@gonzalez.com', $country, 'speedy.gonzalez');
        $role = new RoleBean('Mouse');
        $user->addRole($role);
        $roles = $user->getRoles();
        $this->assertCount(1, $roles);
        $role = $roles[0];
        $this->assertInstanceOf('TheCodingMachine\\TDBM\\Test\\Dao\\Bean\\RoleBean', $role);
        $users = $role->getUsers();
        $this->assertCount(1, $users);
        $this->assertEquals($user, $users[0]);

        $role->removeUser($user);
        $this->assertCount(0, $role->getUsers());
        $this->assertCount(0, $user->getRoles());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testJointureDeleteBeforeGetters()
    {
        $roleDao = new RoleDao($this->tdbmService);
        $userDao = new UserDao($this->tdbmService);
        $role = $roleDao->getById(1);
        $user = $userDao->getById(1);

        // We call removeUser BEFORE calling getUsers
        // This should work as expected.
        $role->removeUser($user);
        $users = $role->getUsers();

        $this->assertCount(1, $users);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testJointureMultiAdd()
    {
        $countryDao = new CountryDao($this->tdbmService);
        $country = $countryDao->getById(1);
        $user = new UserBean('Speedy Gonzalez', 'speedy@gonzalez.com', $country, 'speedy.gonzalez');
        $role = new RoleBean('Mouse');
        $user->addRole($role);
        $role->addUser($user);
        $user->addRole($role);

        $this->assertCount(1, $user->getRoles());
    }

    /**
     * Step 1: we remove the role 1 from user 1 but save role 1.
     * Expected result: no save done.
     *
     * @depends testDaoGeneration
     */
    public function testJointureSave1()
    {
        $roleDao = new RoleDao($this->tdbmService);
        $role = $roleDao->getById(1);
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);

        $this->assertTrue($user->hasRole($role));
        $this->assertTrue($role->hasUser($user));
        $user->removeRole($role);
        $this->assertFalse($user->hasRole($role));
        $this->assertFalse($role->hasUser($user));
        $roleDao->save($role);

        $this->assertEquals(TDBMObjectStateEnum::STATE_DIRTY, $user->_getStatus());
        $this->assertEquals(TDBMObjectStateEnum::STATE_LOADED, $role->_getStatus());
    }

    /**
     * Step 2: we check that nothing was saved
     * Expected result: no save done.
     *
     * @depends testJointureSave1
     */
    public function testJointureSave2()
    {
        $roleDao = new RoleDao($this->tdbmService);
        $role = $roleDao->getById(1);
        $this->assertCount(2, $role->getUsers());
    }

    /**
     * Step 3: we remove the role 1 from user 1 and save user 1.
     * Expected result: save done.
     *
     * @depends testJointureSave2
     */
    public function testJointureSave3()
    {
        $roleDao = new RoleDao($this->tdbmService);
        $role = $roleDao->getById(1);
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);

        $this->assertCount(1, $user->getRoles());
        $user->removeRole($role);
        $this->assertCount(0, $user->getRoles());
        $userDao->save($user);
        $this->assertCount(0, $user->getRoles());
    }

    /**
     * Step 4: we check that save was done
     * Expected result: save done.
     *
     * @depends testJointureSave3
     */
    public function testJointureSave4()
    {
        $roleDao = new RoleDao($this->tdbmService);
        $role = $roleDao->getById(1);
        $this->assertCount(1, $role->getUsers());
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);
        $this->assertCount(0, $user->getRoles());
    }

    /**
     * Step 5: we add the role 1 from user 1 and save user 1.
     * Expected result: save done.
     *
     * @depends testJointureSave4
     */
    public function testJointureSave5()
    {
        $roleDao = new RoleDao($this->tdbmService);
        $role = $roleDao->getById(1);
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);

        $user->addRole($role);
        $this->assertCount(1, $user->getRoles());
        $userDao->save($user);
    }

    /**
     * Step 6: we check that save was done
     * Expected result: save done.
     *
     * @depends testJointureSave5
     */
    public function testJointureSave6()
    {
        $roleDao = new RoleDao($this->tdbmService);
        $role = $roleDao->getById(1);
        $this->assertCount(2, $role->getUsers());
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);
        $this->assertCount(1, $user->getRoles());
    }

    /**
     * Step 7: we add a new role to user 1 and save user 1.
     * Expected result: save done, including the new role.
     *
     * @depends testJointureSave6
     */
    public function testJointureSave7()
    {
        $role = new RoleBean('my new role');
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);

        $user->addRole($role);
        $userDao->save($user);

        $this->assertEquals(TDBMObjectStateEnum::STATE_LOADED, $role->_getStatus());
    }

    /**
     * Step 8: we check that save was done
     * Expected result: save done.
     *
     * @depends testJointureSave7
     */
    public function testJointureSave8()
    {
        $roleDao = new RoleDao($this->tdbmService);
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);

        $roles = $user->getRoles();
        foreach ($roles as $role) {
            if ($role->getName() === 'my new role') {
                $selectedRole = $role;
                break;
            }
        }
        $this->assertNotNull($selectedRole);

        $this->assertCount(2, $user->getRoles());

        // Expected: relationship removed!
        $roleDao->delete($selectedRole);

        $this->assertCount(1, $user->getRoles());
    }

    /**
     * Step 9: Let's test the setXXX method.
     *
     * @depends testJointureSave8
     */
    public function testJointureSave9()
    {
        $roleDao = new RoleDao($this->tdbmService);
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);

        // At this point, user 1 is linked to role 1.
        // Let's bind it to role 2.
        $user->setRoles([$roleDao->getById(2)]);
        $userDao->save($user);
    }

    /**
     * Step 10: Let's check results of 9.
     *
     * @depends testJointureSave9
     */
    public function testJointureSave10()
    {
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);

        $roles = $user->getRoles();

        $this->assertCount(1, $roles);
        $this->assertEquals(2, $roles[0]->getId());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindOrderBy()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByAlphabeticalOrder();

        $this->assertCount(6, $users);
        $this->assertEquals('bill.shakespeare', $users[0]->getLogin());
        $this->assertEquals('jean.dupont', $users[1]->getLogin());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindFromSqlOrderBy()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersFromSqlByAlphabeticalOrder();

        $this->assertCount(6, $users);
        $this->assertEquals('bill.shakespeare', $users[0]->getLogin());
        $this->assertEquals('jean.dupont', $users[1]->getLogin());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindFromSqlOrderByJoinRole()
    {
        $roleDao = new TestRoleDao($this->tdbmService);
        $roles = $roleDao->getRolesByRightCanSing('roles.name DESC');

        $this->assertCount(2, $roles);
        $this->assertEquals('Singers', $roles[0]->getName());
        $this->assertEquals('Admins', $roles[1]->getName());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindFilters()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByLoginStartingWith('bill');

        $this->assertCount(1, $users);
        $this->assertEquals('bill.shakespeare', $users[0]->getLogin());
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     * @depends testDaoGeneration
     */
    public function testFindMode()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByLoginStartingWith('bill', TDBMService::MODE_CURSOR);

        $users[0];
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindAll()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->findAll();

        $this->assertCount(6, $users);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindOne()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $user = $userDao->getUserByLogin('bill.shakespeare');

        $this->assertEquals('bill.shakespeare', $user->getLogin());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testJsonEncodeBean()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $user = $userDao->getUserByLogin('bill.shakespeare');

        $jsonEncoded = json_encode($user);
        $userDecoded = json_decode($jsonEncoded, true);

        $this->assertEquals('bill.shakespeare', $userDecoded['login']);

        // test serialization of dates.
        $this->assertTrue(is_string($userDecoded['createdAt']));
        $this->assertEquals('2015-10-24', (new \DateTimeImmutable($userDecoded['createdAt']))->format('Y-m-d'));
        $this->assertNull($userDecoded['modifiedAt']);

        // testing many to 1 relationships
        $this->assertEquals('UK', $userDecoded['country']['label']);

        // testing many to many relationships
        $this->assertCount(1, $userDecoded['roles']);
        $this->assertArrayNotHasKey('users', $userDecoded['roles'][0]);
        $this->assertArrayNotHasKey('rights', $userDecoded['roles'][0]);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testNullableForeignKey()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $user = $userDao->getUserByLogin('john.smith');

        $this->assertNull(null, $user->getManager());

        $jsonEncoded = json_encode($user);
        $userDecoded = json_decode($jsonEncoded, true);

        $this->assertNull(null, $userDecoded['manager']);
    }

    /**
     * Test that setting (and saving) objects' references (foreign keys relations) to null is working.
     *
     * @depends testDaoGeneration
     */
    public function testSetToNullForeignKey()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $user = $userDao->getUserByLogin('john.smith');
        $manager = $userDao->getUserByLogin('jean.dupont');

        $user->setManager($manager);
        $userDao->save($user);

        $user->setManager(null);
        $userDao->save($user);
    }

    /**
     * @depends testDaoGeneration
     * @expectedException \Mouf\Database\SchemaAnalyzer\SchemaAnalyzerTableNotFoundException
     * @expectedExceptionMessage Could not find table 'contacts'. Did you mean 'contact'?
     */
    public function testQueryOnWrongTableName()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersWrongTableName();
        $users->count();
    }

    /**
     * @depends testDaoGeneration
     */
    /*public function testQueryNullForeignKey()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByManagerId(null);
        $this->assertCount(3, $users);
    }*/

    /**
     * @depends testDaoGeneration
     */
    public function testInnerJsonEncode()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $user = $userDao->getUserByLogin('bill.shakespeare');

        $jsonEncoded = json_encode(['user' => $user]);
        $msgDecoded = json_decode($jsonEncoded, true);

        $this->assertEquals('bill.shakespeare', $msgDecoded['user']['login']);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testArrayJsonEncode()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByLoginStartingWith('bill');

        $jsonEncoded = json_encode($users);
        $msgDecoded = json_decode($jsonEncoded, true);

        $this->assertCount(1, $msgDecoded);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCursorJsonEncode()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByLoginStartingWith('bill', TDBMService::MODE_CURSOR);

        $jsonEncoded = json_encode($users);
        $msgDecoded = json_decode($jsonEncoded, true);

        $this->assertCount(1, $msgDecoded);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testPageJsonEncode()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByLoginStartingWith('bill');

        $jsonEncoded = json_encode($users->take(0, 1));
        $msgDecoded = json_decode($jsonEncoded, true);

        $this->assertCount(1, $msgDecoded);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFirst()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByLoginStartingWith('bill');

        $bill = $users->first();
        $this->assertEquals('bill.shakespeare', $bill->getLogin());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFirstNull()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByLoginStartingWith('mike');

        $user = $users->first();
        $this->assertNull($user);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCloneBeanAttachedBean()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $user = $userDao->getUserByLogin('bill.shakespeare');
        $this->assertEquals(4, $user->getId());
        $user2 = clone $user;
        $this->assertNull($user2->getId());
        $this->assertEquals('bill.shakespeare', $user2->getLogin());
        $this->assertEquals('Bill Shakespeare', $user2->getName());
        $this->assertEquals('UK', $user2->getCountry()->getLabel());

        // MANY 2 MANY must be duplicated
        $this->assertEquals('Writers', $user2->getRoles()[0]->getName());

        // Let's test saving this clone
        $user2->setLogin('william.shakespeare');
        $userDao->save($user2);

        $user3 = $userDao->getUserByLogin('william.shakespeare');
        $this->assertTrue($user3 === $user2);
        $userDao->delete($user3);

        // Finally, let's test the origin user still exists!
        $user4 = $userDao->getUserByLogin('bill.shakespeare');
        $this->assertEquals('bill.shakespeare', $user4->getLogin());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCloneNewBean()
    {
        $countryDao = new CountryDao($this->tdbmService);
        $roleDao = new RoleDao($this->tdbmService);
        $role = $roleDao->getById(1);

        $userBean = new UserBean('John Doe', 'john@doe.com', $countryDao->getById(2), 'john.doe');
        $userBean->addRole($role);

        $user2 = clone $userBean;

        $this->assertNull($user2->getId());
        $this->assertEquals('john.doe', $user2->getLogin());
        $this->assertEquals('John Doe', $user2->getName());
        $this->assertEquals('UK', $user2->getCountry()->getLabel());

        // MANY 2 MANY must be duplicated
        $this->assertEquals($role->getName(), $user2->getRoles()[0]->getName());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCascadeDelete()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $countryDao = new CountryDao($this->tdbmService);

        $spain = new CountryBean('Spain');
        $sanchez = new UserBean('Manuel Sanchez', 'manuel@sanchez.com', $spain, 'manuel.sanchez');

        $countryDao->save($spain);
        $userDao->save($sanchez);

        $speedy2 = $userDao->getUserByLogin('manuel.sanchez');
        $this->assertTrue($sanchez === $speedy2);

        $exceptionTriggered = false;
        try {
            $countryDao->delete($spain);
        } catch (ForeignKeyConstraintViolationException $e) {
            $exceptionTriggered = true;
        }
        $this->assertTrue($exceptionTriggered);

        $countryDao->delete($spain, true);

        // Let's check that speed gonzalez was removed.
        $speedy3 = $userDao->getUserByLogin('manuel.sanchez');
        $this->assertNull($speedy3);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testDiscardChanges()
    {
        $contactDao = new ContactDao($this->tdbmService);
        $contactBean = $contactDao->getById(1);

        $oldName = $contactBean->getName();

        $contactBean->setName('MyNewName');

        $contactBean->discardChanges();

        $this->assertEquals($oldName, $contactBean->getName());
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     * @depends testDaoGeneration
     */
    public function testDiscardChangesOnNewBeanFails()
    {
        $person = new PersonBean('John Foo', new \DateTimeImmutable());
        $person->discardChanges();
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     * @depends testDaoGeneration
     */
    public function testDiscardChangesOnDeletedBeanFails()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $countryDao = new CountryDao($this->tdbmService);

        $sanchez = new UserBean('Manuel Sanchez', 'manuel@sanchez.com', $countryDao->getById(1), 'manuel.sanchez');

        $userDao->save($sanchez);

        $userDao->delete($sanchez);

        // Cannot discard changes on a bean that is already deleted.
        $sanchez->discardChanges();
    }

    /**
     * @depends testDaoGeneration
     */
    public function testUniqueIndexBasedSearch()
    {
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->findOneByLogin('bill.shakespeare');

        $this->assertEquals('bill.shakespeare', $user->getLogin());
        $this->assertEquals('Bill Shakespeare', $user->getName());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testMultiColumnsIndexBasedSearch()
    {
        $countryDao = new CountryDao($this->tdbmService);
        $userDao = new UserDao($this->tdbmService);
        $users = $userDao->findByStatusAndCountry('on', $countryDao->getById(1));

        $this->assertEquals('jean.dupont', $users[0]->getLogin());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCreationInNullableDate()
    {
        $roleDao = new RoleDao($this->tdbmService);

        $role = new RoleBean('newbee');
        $roleDao->save($role);

        $this->assertNull($role->getCreatedAt());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testUpdateInNullableDate()
    {
        $roleDao = new RoleDao($this->tdbmService);

        $role = new RoleBean('newbee');
        $roleDao->save($role);

        $role->setCreatedAt(null);
        $roleDao->save($role);
        $this->assertNull($role->getCreatedAt());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindFromSql()
    {
        $roleDao = new TestRoleDao($this->tdbmService);

        $roles = $roleDao->getRolesByRightCanSing();
        $this->assertCount(2, $roles);
        $this->assertInstanceOf(RoleBean::class, $roles[0]);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindOneFromSql()
    {
        $roleDao = new TestRoleDao($this->tdbmService);

        $role = $roleDao->getRoleByRightCanSingAndNameSinger();
        $this->assertInstanceOf(RoleBean::class, $role);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCreateEmptyExtendedBean()
    {
        // This test cases checks issue https://github.com/thecodingmachine/database.tdbm/issues/92

        $dogDao = new DogDao($this->tdbmService);

        // We are not filling no field that is part of dog table.
        $dog = new DogBean('Youki');
        $dog->setOrder(1);

        $dogDao->save($dog);
    }

    /**
     * @depends testCreateEmptyExtendedBean
     */
    public function testFetchEmptyExtendedBean()
    {
        // This test cases checks issue https://github.com/thecodingmachine/database.tdbm/issues/92

        $animalDao = new AnimalDao($this->tdbmService);

        // We are not filling no field that is part of dog table.
        $animalBean = $animalDao->getById(1);

        $this->assertInstanceOf(DogBean::class, $animalBean);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testTwoBranchesHierarchy()
    {
        // This test cases checks issue https://github.com/thecodingmachine/mouf/issues/131

        $catDao = new CatDao($this->tdbmService);

        // We are not filling no field that is part of dog table.
        $cat = new CatBean('Mew');
        $cat->setOrder(2);

        $catDao->save($cat);
    }

    /**
     * @depends testTwoBranchesHierarchy
     */
    public function testFetchTwoBranchesHierarchy()
    {
        // This test cases checks issue https://github.com/thecodingmachine/mouf/issues/131

        $animalDao = new AnimalDao($this->tdbmService);

        $animalBean = $animalDao->getById(2);

        $this->assertInstanceOf(CatBean::class, $animalBean);
        /* @var $animalBean CatBean */
        $animalBean->setCutenessLevel(999);

        $animalDao->save($animalBean);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testExceptionOnGetById()
    {
        $countryDao = new CountryDao($this->tdbmService);
        $this->expectException(\TypeError::class);
        $countryDao->getById(null);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testDisconnectedManyToOne()
    {
        // This test cases checks issue https://github.com/thecodingmachine/database.tdbm/issues/99

        $country = new CountryBean('Spain');

        $user = new UserBean('John Doe', 'john@doe.com', $country, 'john.doe');

        $this->assertCount(1, $country->getUsers());
        $this->assertSame($user, $country->getUsers()[0]);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testOrderByExternalCol()
    {
        // This test cases checks issue https://github.com/thecodingmachine/database.tdbm/issues/106

        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByCountryName();

        $this->assertEquals('UK', $users[0]->getCountry()->getLabel());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testResultIteratorSort()
    {
        $userDao = new UserDao($this->tdbmService);
        $users = $userDao->findAll()->withOrder('country.label DESC');

        $this->assertEquals('UK', $users[0]->getCountry()->getLabel());

        $users = $users->withOrder('country.label ASC');
        $this->assertEquals('France', $users[0]->getCountry()->getLabel());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testResultIteratorWithParameters()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByLoginStartingWith()->withParameters(['login' => 'bill%']);
        $this->assertEquals('bill.shakespeare', $users[0]->getLogin());

        $users = $users->withParameters(['login' => 'jean%']);
        $this->assertEquals('jean.dupont', $users[0]->getLogin());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testOrderByExpression()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByReversedCountryName();

        $this->assertEquals('Jamaica', $users[0]->getCountry()->getLabel());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testOrderByException()
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByInvalidOrderBy();
        $this->expectException(TDBMInvalidArgumentException::class);
        $user = $users[0];
    }

    /**
     * @depends testDaoGeneration
     */
    public function testOrderByProtectedColumn()
    {
        $animalDao = new AnimalDao($this->tdbmService);
        $animals = $animalDao->findAll();
        $animals = $animals->withOrder('`order` ASC');

        $this->assertInstanceOf(DogBean::class, $animals[0]);
        $this->assertInstanceOf(CatBean::class, $animals[1]);

        $animals = $animals->withOrder('`order` DESC');

        $this->assertInstanceOf(CatBean::class, $animals[0]);
        $this->assertInstanceOf(DogBean::class, $animals[1]);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testGetOnAllNullableValues()
    {
        // Tests that a get performed on a column that has only nullable fields succeeds.
        $allNullable = new AllNullableBean();
        $this->assertNull($allNullable->getId());
        $this->assertNull($allNullable->getLabel());
        $this->assertNull($allNullable->getCountry());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testExceptionOnMultipleInheritance()
    {
        $this->dbConnection->insert('animal', [
            'id' => 99, 'name' => 'Snoofield',
        ]);
        $this->dbConnection->insert('dog', [
            'id' => 99, 'race' => 'dog',
        ]);
        $this->dbConnection->insert('cat', [
            'id' => 99, 'cuteness_level' => 0,
        ]);

        $catched = false;
        try {
            $animalDao = new AnimalDao($this->tdbmService);
            $animalDao->getById(99);
        } catch (TDBMInheritanceException $e) {
            $catched = true;
        }
        $this->assertTrue($catched, 'Exception TDBMInheritanceException was not catched');

        $this->dbConnection->delete('cat', ['id' => 99]);
        $this->dbConnection->delete('dog', ['id' => 99]);
        $this->dbConnection->delete('animal', ['id' => 99]);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testReferenceNotSaved()
    {
        $boatDao = new BoatDao($this->tdbmService);

        $country = new CountryBean('Atlantis');
        $boat = new BoatBean($country, 'Titanic');

        $boatDao->save($boat);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testReferenceDeleted()
    {
        $countryDao = new CountryDao($this->tdbmService);
        $boatDao = new BoatDao($this->tdbmService);

        $country = new CountryBean('Atlantis');
        $countryDao->save($country);

        $boat = new BoatBean($country, 'Titanic');
        $countryDao->delete($country);

        $this->expectException(TDBMMissingReferenceException::class);
        $boatDao->save($boat);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCyclicReferenceWithInheritance()
    {
        $userDao = new UserDao($this->tdbmService);

        $country = new CountryBean('Norrisland');
        $user = new UserBean('Chuck Norris', 'chuck@norris.com', $country, 'chuck.norris');

        $user->setManager($user);

        $this->expectException(TDBMCyclicReferenceException::class);
        $userDao->save($user);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCyclicReference()
    {
        $categoryDao = new CategoryDao($this->tdbmService);

        $category = new CategoryBean('Root');

        $category->setParent($category);

        $this->expectException(TDBMCyclicReferenceException::class);
        $categoryDao->save($category);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCorrectTypeForPrimaryKeyAfterSave()
    {
        $allNullableDao = new AllNullableDao($this->tdbmService);
        $allNullable = new AllNullableBean();
        $allNullableDao->save($allNullable);
        $id = $allNullable->getId();

        $this->assertTrue(is_int($id));
    }

    /**
     * @depends testDaoGeneration
     */
    public function testPSR2Compliance()
    {
        $process = new Process('vendor/bin/php-cs-fixer fix src/Test/  --dry-run --diff --rules=@PSR2');
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            echo $process->getOutput();
            $this->fail('Generated code is not PRS2 compliant');
        }
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindOneByGeneration()
    {
        $reflectionMethod = new \ReflectionMethod(UserBaseDao::class, 'findOneByLogin');
        $parameters = $reflectionMethod->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertSame('login', $parameters[0]->getName());
        $this->assertSame('additionalTablesFetch', $parameters[1]->getName());
    }
}
