<?php
declare(strict_types=1);

/*
 Copyright (C) 2006-2018 David Négrier - THE CODING MACHINE

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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionMethod;
use TheCodingMachine\TDBM\Dao\TestArticleDao;
use TheCodingMachine\TDBM\Dao\TestArticleSubQueryDao;
use TheCodingMachine\TDBM\Dao\TestCountryDao;
use TheCodingMachine\TDBM\Dao\TestRoleDao;
use TheCodingMachine\TDBM\Dao\TestUserDao;
use TheCodingMachine\TDBM\Fixtures\Interfaces\TestUserDaoInterface;
use TheCodingMachine\TDBM\Fixtures\Interfaces\TestUserInterface;
use TheCodingMachine\TDBM\Test\Dao\AlbumDao;
use TheCodingMachine\TDBM\Test\Dao\AllNullableDao;
use TheCodingMachine\TDBM\Test\Dao\AnimalDao;
use TheCodingMachine\TDBM\Test\Dao\ArtistDao;
use TheCodingMachine\TDBM\Test\Dao\BaseObjectDao;
use TheCodingMachine\TDBM\Test\Dao\Bean\AccountBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\AllNullableBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\AnimalBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\Article2Bean;
use TheCodingMachine\TDBM\Test\Dao\Bean\ArticleBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\ArtistBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\BaseObjectBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\BoatBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\CatBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\CategoryBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\CountryBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\DogBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\FileBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\Generated\ArticleBaseBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\Generated\BoatBaseBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\Generated\FileBaseBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\Generated\UserBaseBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\InheritedObjectBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\NodeBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\PersonBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\RefNoPrimKeyBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\RoleBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\StateBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\UserBean;
use TheCodingMachine\TDBM\Test\Dao\BoatDao;
use TheCodingMachine\TDBM\Test\Dao\CatDao;
use TheCodingMachine\TDBM\Test\Dao\CategoryDao;
use TheCodingMachine\TDBM\Test\Dao\CompositeFkSourceDao;
use TheCodingMachine\TDBM\Test\Dao\ContactDao;
use TheCodingMachine\TDBM\Test\Dao\CountryDao;
use TheCodingMachine\TDBM\Test\Dao\DogDao;
use TheCodingMachine\TDBM\Test\Dao\FileDao;
use TheCodingMachine\TDBM\Test\Dao\Generated\UserBaseDao;
use TheCodingMachine\TDBM\Test\Dao\InheritedObjectDao;
use TheCodingMachine\TDBM\Test\Dao\NodeDao;
use TheCodingMachine\TDBM\Test\Dao\RefNoPrimKeyDao;
use TheCodingMachine\TDBM\Test\Dao\RoleDao;
use TheCodingMachine\TDBM\Test\Dao\StateDao;
use TheCodingMachine\TDBM\Test\Dao\UserDao;
use TheCodingMachine\TDBM\Utils\PathFinder\NoPathFoundException;
use TheCodingMachine\TDBM\Utils\PathFinder\PathFinder;
use TheCodingMachine\TDBM\Utils\TDBMDaoGenerator;
use Symfony\Component\Process\Process;

class TDBMDaoGeneratorTest extends TDBMAbstractServiceTest
{
    /** @var TDBMDaoGenerator $tdbmDaoGenerator */
    protected $tdbmDaoGenerator;

    private $rootPath;

    protected function setUp(): void
    {
        parent::setUp();
        $schemaManager = $this->tdbmService->getConnection()->getSchemaManager();
        $schemaAnalyzer = new SchemaAnalyzer($schemaManager);
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($this->tdbmService->getConnection(), new ArrayCache(), $schemaAnalyzer);
        $this->tdbmDaoGenerator = new TDBMDaoGenerator($this->getConfiguration(), $tdbmSchemaAnalyzer);
        $this->rootPath = __DIR__ . '/../';
        //$this->tdbmDaoGenerator->setComposerFile($this->rootPath.'composer.json');
    }

    public function testDaoGeneration(): void
    {
        // Remove all previously generated files.
        $this->recursiveDelete($this->rootPath . 'src/Test/Dao/');
        mkdir($this->rootPath . 'src/Test/Dao/Generated', 0755, true);
        // Let's generate a dummy file to see it is indeed removed.
        $dummyFile = $this->rootPath . 'src/Test/Dao/Generated/foobar.php';
        touch($dummyFile);
        $this->assertFileExists($dummyFile);

        //let's delete the lock file
        $schemaFilePath = TDBMSchemaAnalyzer::getLockFilePath();
        if (file_exists($schemaFilePath)) {
            unlink($schemaFilePath);
        }

        $this->tdbmDaoGenerator->generateAllDaosAndBeans();

        $this->assertFileNotExists($dummyFile);

        //Check that the lock file was generated
        $this->assertFileExists($schemaFilePath);

        // Let's require all files to check they are valid PHP!
        // Test the daoFactory
        require_once $this->rootPath . 'src/Test/Dao/Generated/DaoFactory.php';
        // Test the others

        $beanDescriptors = $this->getDummyGeneratorListener()->getBeanDescriptors();

        foreach ($beanDescriptors as $beanDescriptor) {
            $daoName = $beanDescriptor->getDaoClassName();
            $daoBaseName = $beanDescriptor->getBaseDaoClassName();
            $beanName = $beanDescriptor->getBeanClassName();
            $baseBeanName = $beanDescriptor->getBaseBeanClassName();
            require_once $this->rootPath . 'src/Test/Dao/Bean/Generated/' . $baseBeanName . '.php';
            require_once $this->rootPath . 'src/Test/Dao/Bean/' . $beanName . '.php';
            require_once $this->rootPath . 'src/Test/Dao/Generated/' . $daoBaseName . '.php';
            require_once $this->rootPath . 'src/Test/Dao/' . $daoName . '.php';
        }

        // Check that pivot tables do not generate DAOs or beans.
        $this->assertFalse(class_exists('TheCodingMachine\\TDBM\\Test\\Dao\\RolesRightDao'));
    }

    public function testGenerationException(): void
    {
        $configuration = new Configuration('UnknownVendor\\Dao', 'UnknownVendor\\Bean', self::getConnection(), $this->getNamingStrategy());

        $schemaManager = $this->tdbmService->getConnection()->getSchemaManager();
        $schemaAnalyzer = new SchemaAnalyzer($schemaManager);
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($this->tdbmService->getConnection(), new ArrayCache(), $schemaAnalyzer);
        $tdbmDaoGenerator = new TDBMDaoGenerator($configuration, $tdbmSchemaAnalyzer);
        $this->rootPath = __DIR__ . '/../../../../';
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
    private function recursiveDelete(string $str): bool
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
    public function testGetBeanClassName(): void
    {
        $this->assertEquals(UserBean::class, $this->tdbmService->getBeanClassName('users'));

        // Let's create another TDBMService to test the cache.
        $configuration = new Configuration('TheCodingMachine\\TDBM\\Test\\Dao\\Bean', 'TheCodingMachine\\TDBM\\Test\\Dao', self::getConnection(), $this->getNamingStrategy(), $this->getCache(), null, null, [$this->getDummyGeneratorListener()]);
        $configuration->setPathFinder(new PathFinder(null, dirname(__DIR__, 4)));
        $newTdbmService = new TDBMService($configuration);
        $this->assertEquals(UserBean::class, $newTdbmService->getBeanClassName('users'));
    }

    /**
     * @depends testDaoGeneration
     */
    public function testGeneratedGetById(): void
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
    public function testGeneratedGetByIdLazyLoaded(): void
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
    public function testDefaultValueOnNewBean(): void
    {
        $roleBean = new RoleBean('my_role');
        $this->assertEquals(1, $roleBean->getStatus());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testForeignKeyInBean(): void
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
    public function testNewBeans(): void
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
    public function testDateTimeImmutableGetter(): void
    {
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);

        $this->assertInstanceOf('\DateTimeImmutable', $user->getCreatedAt());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testAssigningNewBeans(): void
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
    public function testUpdatingProtectedColumn(): void
    {
        $userDao = new UserDao($this->tdbmService);
        $userBean = $userDao->findOneByLogin('speedy.gonzalez');
        $userBean->setOrder(2);
        $userDao->save($userBean);
        $this->assertSame(2, $userBean->getOrder());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testAssigningExistingRelationship(): void
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
    public function testDirectReversedRelationship(): void
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
    public function testDeleteInDirectReversedRelationship(): void
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
    public function testJointureGetters(): void
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
    public function testNestedIterationOnAlterableResultIterator(): void
    {
        $countryDao = new CountryDao($this->tdbmService);
        $country = $countryDao->getById(2);

        $count = 0;
        // Let's perform 2 nested calls to check that iterators do not melt.
        foreach ($country->getUsers() as $user) {
            foreach ($country->getUsers() as $user2) {
                $count++;
            }
        }
        // There are 3 users linked to country 2.
        $this->assertSame(9, $count);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testNewBeanConstructor(): void
    {
        $role = new RoleBean('Newrole');
        $this->assertEquals(TDBMObjectStateEnum::STATE_DETACHED, $role->_getStatus());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testJointureAdderOnNewBean(): void
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
    public function testJointureDeleteBeforeGetters(): void
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
    public function testJointureMultiAdd(): void
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
    public function testJointureSave1(): void
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
    public function testJointureSave2(): void
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
    public function testJointureSave3(): void
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
    public function testJointureSave4(): void
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
    public function testJointureSave5(): void
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
    public function testJointureSave6(): void
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
    public function testJointureSave7(): void
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
    public function testJointureSave8(): void
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
    public function testJointureSave9(): void
    {
        $roleDao = new RoleDao($this->tdbmService);
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);

        // At this point, user 1 is linked to role 1.
        // Let's bind it to role 2.
        $user->setRoles([$roleDao->getById(2)]);
        $userDao->save($user);
        $this->assertTrue($user->hasRole($roleDao->getById(2)));
    }

    /**
     * Step 10: Let's check results of 9.
     *
     * @depends testJointureSave9
     */
    public function testJointureSave10(): void
    {
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);

        $roles = $user->getRoles();

        $this->assertCount(1, $roles);
        $this->assertEquals(2, $roles[0]->getId());
    }

    /**
     * Test jointure in a parent table in an inheritance relationship
     *
     * @depends testDaoGeneration
     */
    public function testJointureInParentTable(): void
    {
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->getById(1);

        $boats = $user->getBoats();

        $this->assertCount(1, $boats);
        $this->assertEquals(1, $boats[0]->getId());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindOrderBy(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByAlphabeticalOrder();

        $this->assertCount(6, $users);
        $this->assertEquals('bill.shakespeare', $users[0]->getLogin());
        $this->assertEquals('jean.dupont', $users[1]->getLogin());

        $users = $userDao->getUsersByCountryOrder();
        $this->assertCount(6, $users);
        $countryName1 = $users[0]->getCountry()->getLabel();
        for ($i = 1; $i < 6; $i++) {
            $countryName2 = $users[$i]->getCountry()->getLabel();
            $this->assertLessThanOrEqual(0, strcmp($countryName1, $countryName2));
            $countryName1 = $countryName2;
        }
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindFromSqlOrderBy(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersFromSqlByAlphabeticalOrder();

        $this->assertCount(6, $users);
        $this->assertEquals('bill.shakespeare', $users[0]->getLogin());
        $this->assertEquals('jean.dupont', $users[1]->getLogin());

        $users = $userDao->getUsersFromSqlByCountryOrder();
        $this->assertCount(6, $users);
        $countryName1 = $users[0]->getCountry()->getLabel();
        for ($i = 1; $i < 6; $i++) {
            $countryName2 = $users[$i]->getCountry()->getLabel();
            $this->assertLessThanOrEqual(0, strcmp($countryName1, $countryName2));
            $countryName1 = $countryName2;
        }
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindFromSqlOrderByOnInheritedBean(): void
    {
        $articleDao = new TestArticleDao($this->tdbmService);
        $articles = $articleDao->getArticlesByUserLogin();

        foreach ($articles as $article) {
            var_dump($article);
        }
        $this->assertCount(0, $articles);
    }


    /**
     * @depends testDaoGeneration
     */
    public function testFindFromSqlOrderByJoinRole(): void
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
    public function testFindFromRawSqlOrderByUserCount(): void
    {
        $countryDao = new TestCountryDao($this->tdbmService);
        $countries = $countryDao->getCountriesByUserCount();

        $this->assertCount(4, $countries);
        for ($i = 1; $i < count($countries); $i++) {
            $this->assertLessThanOrEqual($countries[$i - 1]->getUsers()->count(), $countries[$i]->getUsers()->count());
        }
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindFromRawSqlWithUnion(): void
    {
        $countryDao = new TestCountryDao($this->tdbmService);
        $countries = $countryDao->getCountriesUsingUnion();

        $this->assertCount(2, $countries);
        $this->assertEquals(1, $countries[0]->getId());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindFromRawSqlWithSimpleQuery(): void
    {
        $countryDao = new TestCountryDao($this->tdbmService);
        $countries = $countryDao->getCountriesUsingSimpleQuery();

        $this->assertCount(1, $countries);
        $this->assertEquals(1, $countries[0]->getId());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindFromRawSqlWithDistinctQuery(): void
    {
        $countryDao = new TestCountryDao($this->tdbmService);
        $countries = $countryDao->getCountriesUsingDistinctQuery();

        $this->assertCount(1, $countries);
        $this->assertEquals(2, $countries[0]->getId());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindFilters(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByLoginStartingWith('bill');

        $this->assertCount(1, $users);
        $this->assertEquals('bill.shakespeare', $users[0]->getLogin());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindMode(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByLoginStartingWith('bill', TDBMService::MODE_CURSOR);

        $this->expectException('TheCodingMachine\TDBM\TDBMException');
        $users[0];
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindAll(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->findAll();

        $this->assertCount(6, $users);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindOne(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $user = $userDao->getUserByLogin('bill.shakespeare');

        $this->assertEquals('bill.shakespeare', $user->getLogin());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testJsonEncodeBean(): void
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
    public function testNullableForeignKey(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $user = $userDao->getUserByLogin('john.smith');

        $this->assertNull($user->getManager());

        $jsonEncoded = json_encode($user);
        $userDecoded = json_decode($jsonEncoded, true);

        $this->assertNull($userDecoded['manager']);
    }

    /**
     * Test that setting (and saving) objects' references (foreign keys relations) to null is working.
     *
     * @depends testDaoGeneration
     */
    public function testSetToNullForeignKey(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $user = $userDao->getUserByLogin('john.smith');
        $manager = $userDao->getUserByLogin('jean.dupont');

        $user->setManager($manager);
        $userDao->save($user);

        $user->setManager(null);
        $userDao->save($user);
        $this->assertNull($user->getManager());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testQueryOnWrongTableName(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersWrongTableName();
        $this->expectException('Mouf\Database\SchemaAnalyzer\SchemaAnalyzerTableNotFoundException');
        $this->expectExceptionMessage('Could not find table \'contacts\'. Did you mean \'contact\'?');
        $users->count();
    }

    /**
     * @depends testDaoGeneration
     */
    /*public function testQueryNullForeignKey(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByManagerId(null);
        $this->assertCount(3, $users);
    }*/

    /**
     * @depends testDaoGeneration
     */
    public function testInnerJsonEncode(): void
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
    public function testArrayJsonEncode(): void
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
    public function testCursorJsonEncode(): void
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
    public function testPageJsonEncode(): void
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
    public function testInnerResultIteratorCountAfterFetch(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByLoginStartingWith('j')->take(0, 4);
        $users->toArray(); // We force to fetch
        $this->assertEquals(3, $users->count());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFirst(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByLoginStartingWith('bill');

        $bill = $users->first();
        $this->assertEquals('bill.shakespeare', $bill->getLogin());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFirstNull(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByLoginStartingWith('mike');

        $user = $users->first();
        $this->assertNull($user);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCloneBeanAttachedBean(): void
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
    public function testCloneNewBean(): void
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
    public function testCascadeDelete(): void
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
    public function testDiscardChanges(): void
    {
        $contactDao = new ContactDao($this->tdbmService);
        $contactBean = $contactDao->getById(1);

        $oldName = $contactBean->getName();

        $contactBean->setName('MyNewName');

        $contactBean->discardChanges();

        $this->assertEquals($oldName, $contactBean->getName());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testDiscardChangesOnNewBeanFails(): void
    {
        $person = new PersonBean('John Foo', new \DateTimeImmutable());
        $this->expectException('TheCodingMachine\TDBM\TDBMException');
        $person->discardChanges();
    }

    /**
     * @depends testDaoGeneration
     */
    public function testDiscardChangesOnDeletedBeanFails(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $countryDao = new CountryDao($this->tdbmService);

        $sanchez = new UserBean('Manuel Sanchez', 'manuel@sanchez.com', $countryDao->getById(1), 'manuel.sanchez');

        $userDao->save($sanchez);

        $userDao->delete($sanchez);

        $this->expectException('TheCodingMachine\TDBM\TDBMException');
        // Cannot discard changes on a bean that is already deleted.
        $sanchez->discardChanges();
    }

    /**
     * @depends testDaoGeneration
     */
    public function testUniqueIndexBasedSearch(): void
    {
        $userDao = new UserDao($this->tdbmService);
        $user = $userDao->findOneByLogin('bill.shakespeare');

        $this->assertEquals('bill.shakespeare', $user->getLogin());
        $this->assertEquals('Bill Shakespeare', $user->getName());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindOneByRetunsNull(): void
    {
        // Let's assert that the findOneBy... methods can return null.
        $userDao = new UserDao($this->tdbmService);
        $userBean = $userDao->findOneByLogin('not_exist');

        $this->assertNull($userBean);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testMultiColumnsIndexBasedSearch(): void
    {
        $countryDao = new CountryDao($this->tdbmService);
        $userDao = new UserDao($this->tdbmService);
        $users = $userDao->findByStatusAndCountry('on', $countryDao->getById(1));

        $this->assertEquals('jean.dupont', $users[0]->getLogin());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testPartialMultiColumnsIndexBasedSearch(): void
    {
        $userDao = new UserDao($this->tdbmService);
        $users = $userDao->findByStatusAndCountry('on');

        $this->assertCount(2, $users);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCreationInNullableDate(): void
    {
        $roleDao = new RoleDao($this->tdbmService);

        $role = new RoleBean('newbee');
        $roleDao->save($role);

        $this->assertNull($role->getCreatedAt());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testUpdateInNullableDate(): void
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
    public function testFindFromSql(): void
    {
        $roleDao = new TestRoleDao($this->tdbmService);

        $roles = $roleDao->getRolesByRightCanSing();
        $this->assertCount(2, $roles);
        $this->assertInstanceOf(RoleBean::class, $roles[0]);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindOneFromSql(): void
    {
        $roleDao = new TestRoleDao($this->tdbmService);

        $role = $roleDao->getRoleByRightCanSingAndNameSinger();
        $this->assertInstanceOf(RoleBean::class, $role);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCreateEmptyExtendedBean(): void
    {
        // This test cases checks issue https://github.com/thecodingmachine/database.tdbm/issues/92

        $dogDao = new DogDao($this->tdbmService);

        // We are not filling no field that is part of dog table.
        $dog = new DogBean('Youki');
        $dog->setOrder(1);

        $dogDao->save($dog);
        $this->assertNull($dog->getRace());
    }

    /**
     * @depends testCreateEmptyExtendedBean
     */
    public function testFetchEmptyExtendedBean(): void
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
    public function testTwoBranchesHierarchy(): void
    {
        // This test cases checks issue https://github.com/thecodingmachine/mouf/issues/131

        $catDao = new CatDao($this->tdbmService);

        // We are not filling no field that is part of dog table.
        $cat = new CatBean('Mew');
        $cat->setOrder(2);

        $catDao->save($cat);
        $this->assertNotNull($cat->getId());
    }

    /**
     * @depends testTwoBranchesHierarchy
     */
    public function testFetchTwoBranchesHierarchy(): void
    {
        // This test cases checks issue https://github.com/thecodingmachine/mouf/issues/131

        $animalDao = new AnimalDao($this->tdbmService);

        $animalBean = $animalDao->getById(2);

        $this->assertInstanceOf(CatBean::class, $animalBean);
        /* @var $animalBean CatBean */
        $animalBean->setCutenessLevel(999);
        $animalBean->setUppercaseColumn('foobar');

        $animalDao->save($animalBean);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testExceptionOnGetById(): void
    {
        $countryDao = new CountryDao($this->tdbmService);
        $this->expectException(\TypeError::class);
        $countryDao->getById(null);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testDisconnectedManyToOne(): void
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
    public function testOrderByExternalCol(): void
    {
        // This test cases checks issue https://github.com/thecodingmachine/database.tdbm/issues/106

        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByCountryName();

        $this->assertEquals('UK', $users[0]->getCountry()->getLabel());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testResultIteratorSort(): void
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
    public function testResultIteratorWithParameters(): void
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
    public function testOrderByExpression(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByReversedCountryName();

        $this->assertEquals('Jamaica', $users[0]->getCountry()->getLabel());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testOrderByException(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $users = $userDao->getUsersByInvalidOrderBy();
        $this->expectException(TDBMInvalidArgumentException::class);
        $user = $users[0];
    }

    /**
     * @depends testDaoGeneration
     */
    public function testOrderByProtectedColumn(): void
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
    public function testGetOnAllNullableValues(): void
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
    public function testExceptionOnMultipleInheritance(): void
    {
        $connection = self::getConnection();
        self::insert($connection, 'animal', [
            'id' => 99, 'name' => 'Snoofield',
        ]);
        self::insert($connection, 'dog', [
            'id' => 99, 'race' => 'dog',
        ]);
        self::insert($connection, 'cat', [
            'id' => 99, 'cuteness_level' => 0,
        ]);

        $catched = false;
        try {
            $animalDao = new AnimalDao($this->tdbmService);
            $animalDao->getById(99);
        } catch (TDBMInheritanceException $e) {
            $catched = true;
        }
        $this->assertTrue($catched, 'Exception TDBMInheritanceException was not caught');

        self::delete($connection, 'cat', ['id' => 99]);
        self::delete($connection, 'dog', ['id' => 99]);
        self::delete($connection, 'animal', ['id' => 99]);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testReferenceNotSaved(): void
    {
        $boatDao = new BoatDao($this->tdbmService);

        $country = new CountryBean('Atlantis');
        $boat = new BoatBean($country, 'Titanic');

        $boatDao->save($boat);
        $this->assertNotNull($country->getId());
    }

    /**
     * @depends testReferenceNotSaved
     */
    public function testUniqueIndexOnForeignKeyThenScalar(): void
    {
        $boatDao = new BoatDao($this->tdbmService);
        $countryDao = new CountryDao($this->tdbmService);

        $countryBean = $countryDao->findOneByLabel('Atlantis');
        $boatBean = $boatDao->findOneByAnchorageCountryAndName($countryBean, 'Titanic');

        $this->assertNotNull($boatBean);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testReferenceDeleted(): void
    {
        $countryDao = new CountryDao($this->tdbmService);
        $boatDao = new BoatDao($this->tdbmService);

        $country = new CountryBean('Bikini Bottom');
        $countryDao->save($country);

        $boat = new BoatBean($country, 'Squirrel boat');
        $countryDao->delete($country);

        $this->expectException(TDBMMissingReferenceException::class);
        $boatDao->save($boat);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCyclicReferenceWithInheritance(): void
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
    public function testCyclicReference(): void
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
    public function testCorrectTypeForPrimaryKeyAfterSave(): void
    {
        // PosqtgreSQL does not particularly like empty inserts (i.e.: "INSERT INTO all_nullable () VALUES ()" )
        $this->onlyMySql();

        $allNullableDao = new AllNullableDao($this->tdbmService);
        $allNullable = new AllNullableBean();
        $allNullableDao->save($allNullable);
        $id = $allNullable->getId();

        $this->assertTrue(is_int($id));
    }

    /**
     * @depends testDaoGeneration
     */
    public function testPSR2Compliance(): void
    {
        $process = new Process('vendor/bin/php-cs-fixer fix src/Test/  --dry-run --diff --rules=@PSR2');
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            echo $process->getOutput();
            $this->fail('Generated code is not PSR-2 compliant');
        }
        $this->assertTrue($process->isSuccessful());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFindOneByGeneration(): void
    {
        $reflectionMethod = new \ReflectionMethod(UserBaseDao::class, 'findOneByLogin');
        $parameters = $reflectionMethod->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertSame('login', $parameters[0]->getName());
        $this->assertSame('additionalTablesFetch', $parameters[1]->getName());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testUuid(): void
    {
        $article = new ArticleBean('content');
        $this->assertSame('content', $article->getContent());
        $this->assertNotEmpty($article->getId());
        $uuid = Uuid::fromString($article->getId());
        $this->assertSame(1, $uuid->getVersion());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testUuidv4(): void
    {
        $article = new Article2Bean('content');
        $this->assertSame('content', $article->getContent());
        $this->assertNotEmpty($article->getId());
        $uuid = Uuid::fromString($article->getId());
        $this->assertSame(4, $uuid->getVersion());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testTypeHintedConstructors(): void
    {
        $userBaseBeanReflectionConstructor = new \ReflectionMethod(UserBaseBean::class, '__construct');
        $nameParam = $userBaseBeanReflectionConstructor->getParameters()[0];

        $this->assertSame('string', (string)$nameParam->getType());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testSaveTransaction(): void
    {
        $countryDao = new CountryDao($this->tdbmService);

        $boatDao = new BoatDao($this->tdbmService);
        $boatBean = $boatDao->getById(1);
        $boatBean->setName('Bismark');

        $boatBean->getCountry();

        // Let's insert a row without telling TDBM to trigger an error!
        self::insert($this->getConnection(), 'sailed_countries', [
            'boat_id' => 1,
            'country_id' => 2
        ]);

        $boatBean->addCountry($countryDao->getById(2));

        $this->expectException(UniqueConstraintViolationException::class);

        $boatDao->save($boatBean);
    }

    /**
     * @depends testSaveTransaction
     */
    public function testSaveTransaction2(): void
    {
        $boatDao = new BoatDao($this->tdbmService);
        $boatBean = $boatDao->getById(1);

        // The name should not have been saved because the transaction of the previous test should have rollbacked.
        $this->assertNotSame('Bismark', $boatBean->getName());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testForeignKeyPointingToNonPrimaryKey(): void
    {
        $dao = new RefNoPrimKeyDao($this->tdbmService);
        $bean = $dao->getById(1);

        $this->assertSame('foo', $bean->getFrom()->getTo());

        $newBean = new RefNoPrimKeyBean($bean, 'baz');
        $dao->save($newBean);
        $this->assertSame('foo', $newBean->getFrom()->getTo());

        $resultSet = $bean->getRefNoPrimKey();
        $this->assertCount(2, $resultSet);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCloningUuidBean(): void
    {
        $article = new ArticleBean('content');
        $this->assertNotEmpty($article->getId());
        $article2 = clone $article;
        $this->assertNotEmpty($article2->getId());
        $this->assertNotSame($article->getId(), $article2->getId());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testRecursiveSave(): void
    {
        $categoryDao = new CategoryDao($this->tdbmService);

        $root1 = new CategoryBean('Root1');
        $categoryDao->save($root1);
        // Root 2 is not saved yet.
        $root2 = new CategoryBean('Root2');
        $intermediate = new CategoryBean('Intermediate');
        $categoryDao->save($intermediate);

        // Let's switch the parent to a bean in detached state.
        $intermediate->setParent($root2);

        // Now, let's save a new category that references the leaf category.
        $leaf = new CategoryBean('Leaf');
        $leaf->setParent($intermediate);
        $categoryDao->save($leaf);
        $this->assertNull($root2->getId());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testBlob(): void
    {
        $fp = fopen(__FILE__, 'r');
        $file = new FileBean($fp);

        $fileDao = new FileDao($this->tdbmService);

        $fileDao->save($file);

        $loadedFile = $fileDao->getById($file->getId());

        $resource = $loadedFile->getFile();
        $result = fseek($resource, 0);
        $this->assertSame(0, $result);
        $this->assertInternalType('resource', $resource);
        $firstLine = fgets($resource);
        $this->assertSame("<?php\n", $firstLine);
    }

    /**
     * @depends testBlob
     */
    public function testReadBlob(): void
    {
        $fileDao = new FileDao($this->tdbmService);
        $loadedFile = $fileDao->getById(1);

        $resource = $loadedFile->getFile();
        $this->assertInternalType('resource', $resource);
        $firstLine = fgets($resource);
        $this->assertSame("<?php\n", $firstLine);

        stream_get_contents($resource);

        $loadedFile->setId($loadedFile->getId());

        $fileDao->save($loadedFile);
    }

    /**
     * @depends testReadBlob
     */
    public function testReadAndSaveBlob(): void
    {
        $fileDao = new FileDao($this->tdbmService);
        $loadedFile = $fileDao->getById(1);

        $resource = $loadedFile->getFile();

        $firstLine = fgets($resource);
        $this->assertSame("<?php\n", $firstLine);
    }

    /**
     * @depends testReadBlob
     */
    public function testProtectedGetterSetter(): void
    {
        $md5Getter = new ReflectionMethod(FileBaseBean::class, 'getMd5');
        $md5Setter = new ReflectionMethod(FileBaseBean::class, 'setMd5');

        $this->assertTrue($md5Getter->isProtected());
        $this->assertTrue($md5Setter->isProtected());

        $md5Getter2 = new ReflectionMethod(FileBaseBean::class, 'getArticle');
        $md5Setter2 = new ReflectionMethod(FileBaseBean::class, 'setArticle');

        $this->assertTrue($md5Getter2->isProtected());
        $this->assertTrue($md5Setter2->isProtected());

        $oneToManyGetter = new ReflectionMethod(ArticleBaseBean::class, 'getFiles');
        $this->assertTrue($oneToManyGetter->isProtected());

        $fileDao = new FileDao($this->tdbmService);
        $loadedFile = $fileDao->getById(1);

        // The md5 and article columns are not JSON serialized
        $this->assertSame([
            'id' => 1,
        ], $loadedFile->jsonSerialize());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testBlobResourceException(): void
    {
        $this->expectException(TDBMInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid argument passed to \'TheCodingMachine\\TDBM\\Test\\Dao\\Bean\\Generated\\FileBaseBean::setFile\'. Expecting a resource. Got a string.');
        new FileBean('foobar');
    }

    /**
     * @depends testDaoGeneration
     */
    public function testFilterBag(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $countryDao = new CountryDao($this->tdbmService);

        $country = $countryDao->getById(2);

        // Let's test filter bags by bean and filter bag with many values.
        $users = $userDao->getUsersByComplexFilterBag($country, ['John Doe', 'Jane Doe']);

        $this->assertCount(1, $users);
        $this->assertSame('John Doe', $users[0]->getName());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testDecimalIsMappedToString(): void
    {
        $reflectionClass = new \ReflectionClass(BoatBaseBean::class);
        $this->assertSame('string', (string) $reflectionClass->getMethod('getLength')->getReturnType());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testInsertMultiPrimaryKeysBean(): void
    {
        $countryDao = new CountryDao($this->tdbmService);

        $country = $countryDao->getById(1);

        $stateDao = new StateDao($this->tdbmService);
        $state = new StateBean($country, 'IDF', 'Ile de France');
        $stateDao->save($state);

        $this->assertSame($state, $stateDao->findAll()[0]);
    }

    /**
     * @depends testInsertMultiPrimaryKeysBean
     */
    public function testDeleteMultiPrimaryKeysBean(): void
    {
        $stateDao = new StateDao($this->tdbmService);

        $state = $stateDao->findAll()[0];
        $stateDao->delete($state);
        $this->assertCount(0, $stateDao->findAll());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCompositePrimaryKeyGetter(): void
    {
        $stateDao = new StateDao($this->tdbmService);
        $country = new CountryBean('USA');
        $stateBean = new StateBean($country, 'CA', 'California');
        $stateDao->save($stateBean);
        $this->assertSame($stateBean, $stateDao->getById($country->getId(), 'CA'));
    }

    /**
     * @depends testDaoGeneration
     */
    public function testSortOnInheritedTable(): void
    {
        $animalDao = new AnimalDao($this->tdbmService);

        // Let's insert an animal that is nothing.
        $animal = new AnimalBean('Mickey');
        $animalDao->save($animal);

        $animals = $animalDao->findAll()->withOrder('dog.race ASC');

        $animalsArr = $animals->toArray();
        $this->assertCount(3, $animalsArr);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testJsonKey(): void
    {
        $node = new NodeBean('foo.html');
        $json = $node->jsonSerialize();
        self::assertTrue(isset($json['basename']));
        self::assertEquals('foo.html', $json['basename']);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testJsonIgnore(): void
    {
        $nodeDao = new NodeDao($this->tdbmService);
        $index = $nodeDao->getById(6);
        $json = $index->jsonSerialize();
        // Ignored scalar 'id'
        self::assertTrue(!isset($json['id']));
        // Ignored object 'root'
        self::assertTrue(!isset($json['root']));
        self::assertTrue(isset($json['guests']));
        self::assertTrue(!empty($json['guests']));
        $account = $index->getAccounts()[0];
        $json = $account->jsonSerialize();
        // Ignored array 'nodes' (from nodes_users table)
        self::assertTrue(!isset($json['nodes']));
    }

    /**
     * @depends testDaoGeneration
     */
    public function testJsonInclude(): void
    {
        $nodeDao = new NodeDao($this->tdbmService);
        $index = $nodeDao->getById(6);
        $json = $index->jsonSerialize();
        // Whole chain of parents should be serialized
        self::assertTrue(isset($json['parent']));
        self::assertTrue(isset($json['parent']['parent']));
        self::assertTrue(isset($json['parent']['parent']['parent']));
        self::assertEquals('/', $json['parent']['parent']['parent']['basename']);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testJsonRecursive(): void
    {
        $nodeDao = new NodeDao($this->tdbmService);
        $index = $nodeDao->getById(8);
        $json = $index->jsonSerialize();
        // Original chain of aliases is recursively serialized, ...
        self::assertTrue(isset($json['alias']));
        self::assertTrue(isset($json['alias']['alias']));
        self::assertEquals('index.html', $json['alias']['alias']['basename']);
        // ... each alias even serializes its parents, ...
        self::assertTrue(isset($json['alias']['alias']['parent']['parent']));
        // ... however, parents aliases chains have just their foreign key (id), as parents are serialized with $stopRecursion=true
        self::assertEquals(3, $json['alias']['alias']['parent']['parent']['alias']['id']);
        self::assertCount(1, $json['alias']['alias']['parent']['parent']['alias']);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testJsonFormat(): void
    {
        $nodeDao = new NodeDao($this->tdbmService);
        $index = $nodeDao->getById(6);
        $json = $index->jsonSerialize();
        self::assertTrue(isset($json['size']));
        self::assertEquals('512 o', $json['size']);
        self::assertEquals('42.50g', $json['weight']);
        self::assertEquals($index->getCreatedAt()->format('Y-m-d'), $json['createdAt']);
        self::assertEquals($index->getOwner()->getName(), $json['owner']);
        self::assertEquals($index->getAccounts()[1]->getName(), $json['guests'][1]);
        self::assertTrue(isset($json['entries']));
        self::assertEquals('Hello, World', $json['entries'][1]);
        $www = $index->getParent();
        $json = $www->jsonSerialize();
        self::assertEquals('0 o', $json['size']);
        self::assertNull($json['weight']);
        self::assertNull($json['owner']);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testJsonCollection(): void
    {
        $artists = new ArtistDao($this->tdbmService);
        $pinkFloyd = $artists->getById(1);
        $animals =  $pinkFloyd->getAlbums()[0];
        $json = $pinkFloyd->jsonSerialize();
        // Collection name properly handled ('discography' instead of default 'albums')
        self::assertTrue(isset($json['discography']));
        self::assertEquals($animals->getTitle(), $json['discography'][0]['title']);
        // Make sure top object have just its primary key
        self::assertEquals(1, $json['discography'][0]['artist']['id']);
        self::assertCount(1, $json['discography'][0]['artist']);
        $json = $animals->jsonSerialize();
        // Nevertheless, artist should be serialized in album as top object...
        self::assertTrue(isset($json['artist']));
        // ... as should be tracks...
        self::assertTrue(isset($json['tracks'][0]));
        self::assertEquals('Pigs on the Wing 1', $json['tracks'][0]['title']);
        // ... and, ultimately, list of featuring artists, since feat is included
        self::assertTrue(isset($json['tracks'][0]['feat'][0]));
        self::assertEquals('Roger Waters', $json['tracks'][0]['feat'][0]['name']);
    }

    public function testFloydHasNoParent(): void
    {
        $artists = new ArtistDao($this->tdbmService);
        $pinkFloyd = $artists->getById(1);
        $parents = $pinkFloyd->getParents();

        $this->assertEquals([], $parents);
    }

    public function testFloydHasOneChild(): void
    {
        $artists = new ArtistDao($this->tdbmService);
        $pinkFloyd = $artists->getById(1);
        $children = $pinkFloyd->getChildrenByArtistsRelations();

        $this->assertEquals(1, count($children));
        $this->assertEquals(2, $children[0]->getId());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testAddInterfaceAnnotation(): void
    {
        if (!$this->tdbmService->getConnection()->getDatabasePlatform() instanceof MySqlPlatform) {
            // See https://github.com/doctrine/dbal/pull/3512
            $this->markTestSkipped('Only MySQL supports table level comments');
        }

        $refClass = new ReflectionClass(UserBaseBean::class);
        $this->assertTrue($refClass->implementsInterface(TestUserInterface::class));
    }

    /**
     * @depends testDaoGeneration
     */
    public function testAddInterfaceOnDaoAnnotation(): void
    {
        if (!$this->tdbmService->getConnection()->getDatabasePlatform() instanceof MySqlPlatform) {
            // See https://github.com/doctrine/dbal/pull/3512
            $this->markTestSkipped('Only MySQL supports table level comments');
        }

        $refClass = new ReflectionClass(UserBaseDao::class);
        $this->assertTrue($refClass->implementsInterface(TestUserDaoInterface::class));
    }

    /**
     * @depends testDaoGeneration
     */
    public function testTrait(): void
    {
        if (!$this->tdbmService->getConnection()->getDatabasePlatform() instanceof MySqlPlatform) {
            // See https://github.com/doctrine/dbal/pull/3512
            $this->markTestSkipped('Only MySQL supports table level comments');
        }

        $userDao = new UserDao($this->tdbmService);
        $userBean = $userDao->getById(1);

        $this->assertSame('TestOtherUserTrait', $userBean->method1());
        $this->assertSame('TestUserTrait', $userBean->method1renamed());

        $refClass = new ReflectionClass(UserBaseDao::class);
        $this->assertTrue($refClass->hasMethod('findNothing'));
    }

    /**
     * @depends testDaoGeneration
     */
    public function testNonInstantiableAbstractDaosAndBeans(): void
    {
        $refClass = new ReflectionClass(UserBaseDao::class);
        $this->assertFalse($refClass->isInstantiable());

        $refClass = new ReflectionClass(UserBaseBean::class);
        $this->assertFalse($refClass->isInstantiable());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testCanNullifyBlob(): void
    {
        $article = new ArticleBean('content');
        $fp = fopen(__FILE__, 'r');
        $article->setAttachment($fp);
        $article->setAttachment(null);
        $this->assertNull($article->getAttachment(null));
        fclose($fp);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testOptionnalParametersCanBeNullInFindOneBy()
    {
        $albumDao = new AlbumDao($this->tdbmService);
        $artist = new ArtistBean('Marcel');

        $albumDao->findOneByArtistAndNode($artist, null);
        $this->assertEquals(1, 1);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testRequiredParametersCannotBeNullInFindOneBy()
    {
        $albumDao = new AlbumDao($this->tdbmService);
        $artist = new ArtistBean('Marcel');
        $account = new AccountBean('Jamie');

        $albumDao->findOneByArtistAndAccount($artist, $account);

        $this->expectException('TypeError');
        $albumDao->findOneByArtistAndAccount($artist, null);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testLazyLoad(): void
    {
        $roleDao = new RoleDao($this->tdbmService);
        $roleBean = $roleDao->getById(1, true);

        $this->assertSame(TDBMObjectStateEnum::STATE_NOT_LOADED, $roleBean->_getDbRows()['roles']->_getStatus());
        $roleBean->getId();
        $this->assertSame(TDBMObjectStateEnum::STATE_NOT_LOADED, $roleBean->_getDbRows()['roles']->_getStatus());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testOneToOneInverseRelationGetter(): void
    {
        $objectBaseDao = new BaseObjectDao($this->tdbmService);
        $objectInheritedDao = new InheritedObjectDao($this->tdbmService);
        $objectBase = new BaseObjectBean('label');
        $objectBaseDao->save($objectBase);
        $this->assertNull($objectBase->getInheritedObject());
        $objectInherited = new InheritedObjectBean($objectBase);
        $objectInheritedDao->save($objectInherited);
        $this->assertSame($objectInherited, $objectBase->getInheritedObject());
        $this->assertEquals(1, $objectBase->jsonSerialize()['inheritedObject']['id']);
    }

    public function testLazyStopRecursion(): void
    {
        $albumDao = new AlbumDao($this->tdbmService);
        $albumBean = $albumDao->getById(1);
        $json = $albumBean->jsonSerialize(true);
        $this->assertArrayHasKey('id', $json['artist']);
        $this->assertArrayNotHasKey('name', $json['artist']);
    }

    public function testLazyStopRecursionOnCompositeForeignKey(): void
    {
        $compositeFkSourceDao = new CompositeFkSourceDao($this->tdbmService);
        $compositeFkSourceBean = $compositeFkSourceDao->getById(1);
        $json = $compositeFkSourceBean->jsonSerialize(true);
        $this->assertEquals(1, $json['compositeFkTarget']['id1']);
        $this->assertEquals(1, $json['compositeFkTarget']['id2']);
    }

    public function testMethodNameConflictsBetweenRegularAndAutoPivotProperties()
    {
        $artist = new ArtistBean('Super');
        $artist->getChildren(); // regular property
        $artist->getChildrenByArtistId(); // one-to-may relationship
        $artist->getChildrenByArtistsRelations(); // auto-pivot relationship
        $this->assertEquals(1, 1);
    }

    /**
     * @depends testDaoGeneration
     */
    public function testSQLCountWithArray(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $countryDao = new CountryDao($this->tdbmService);

        $country = $countryDao->getById(2);

        // Let's test filter bags by bean and filter bag with many values.
        $users = $userDao->getUsersByComplexFilterBag($country, ['John Doe', 'John Smith'])->take(0, 1);
        $this->assertEquals(1, $users->count());
    }

    /**
     * @depends testDaoGeneration
     */
    public function testSubQueryWithFind(): void
    {
        $userDao = new TestUserDao($this->tdbmService);
        $articleDao = new TestArticleSubQueryDao($this->tdbmService, $userDao);

        $bill = $userDao->getById(4);
        $article = new ArticleBean('Foo');
        $article->setAuthor($bill);
        $articleDao->save($article);

        $results = $articleDao->getArticlesByUserLoginStartingWith('bill');

        $this->assertCount(1, $results);
        $this->assertSame('Foo', $results[0]->getContent());
    }

    public function testSubQueryExceptionOnPrimaryKeysWithMultipleColumns(): void
    {
        $stateDao = new StateDao($this->tdbmService);
        $states = $stateDao->findAll();
        $this->expectException(TDBMException::class);
        $this->expectExceptionMessage('You cannot use in a sub-query a table that has a primary key on more that 1 column.');
        $states->_getSubQuery();
    }
}
