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

use Psr\Log\LogLevel;
use Wa72\SimpleLogger\ArrayLogger;

class TDBMServiceTest extends TDBMAbstractServiceTest
{
    public function testGetLinkBetweenInheritedTables()
    {
        $this->assertEquals(['users', 'contact', 'person'], $this->tdbmService->_getLinkBetweenInheritedTables(['contact', 'users']));
        $this->assertEquals(['users', 'contact', 'person'], $this->tdbmService->_getLinkBetweenInheritedTables(['users', 'contact']));
        $this->assertEquals(['contact', 'person'], $this->tdbmService->_getLinkBetweenInheritedTables(['person', 'contact']));
        $this->assertEquals(['users', 'contact', 'person'], $this->tdbmService->_getLinkBetweenInheritedTables(['users']));
        $this->assertEquals(['person'], $this->tdbmService->_getLinkBetweenInheritedTables(['person']));
    }

    public function testGetRelatedTablesByInheritance()
    {
        $contactRelatedTables = $this->tdbmService->_getRelatedTablesByInheritance('contact');
        $this->assertCount(3, $contactRelatedTables);
        $this->assertContains('users', $contactRelatedTables);
        $this->assertContains('contact', $contactRelatedTables);
        $this->assertContains('person', $contactRelatedTables);
        $this->assertEquals(['person', 'contact', 'users'], $this->tdbmService->_getRelatedTablesByInheritance('users'));
        $this->assertEquals(['person', 'contact', 'users'], $this->tdbmService->_getRelatedTablesByInheritance('person'));
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     */
    public function testGetPrimaryKeysFromIndexedPrimaryKeysException()
    {
        $this->tdbmService->_getPrimaryKeysFromIndexedPrimaryKeys('users', [5, 4]);
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     */
    public function testGetLinkBetweenInheritedTablesExceptions()
    {
        $this->tdbmService->_getLinkBetweenInheritedTables(['contact', 'country']);
    }

    public function testHashPrimaryKey()
    {
        $reflection = new \ReflectionClass(get_class($this->tdbmService));
        $method = $reflection->getMethod('getObjectHash');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->tdbmService, [
            ['id' => 42],
        ]);
        $this->assertEquals(42, $result);

        // Check that multiple primary keys are insensitive to column order
        $result1 = $method->invokeArgs($this->tdbmService, [
            ['id1' => 42, 'id2' => 24],
        ]);
        $result2 = $method->invokeArgs($this->tdbmService, [
            ['id2' => 24, 'id1' => 42],
        ]);
        $this->assertEquals($result1, $result2);
    }

    public function testInsertAndUpdateAndDelete()
    {
        $object = new TDBMObject('users');
        $object->setProperty('login', 'john.doe');
        $object->setProperty('country_id', 3);
        $object->setProperty('name', 'John Doe', 'person');
        $object->setProperty('email', 'john@doe.com', 'contact');

        $this->tdbmService->save($object);

        $this->assertNotEmpty($object->getProperty('id', 'person'));
        $this->assertNotEmpty($object->getProperty('id', 'users'));
        $this->assertEquals($object->getProperty('id', 'person'), $object->getProperty('id', 'users'));

        $object->setProperty('country_id', 2, 'users');

        $this->tdbmService->save($object);

        $this->tdbmService->delete($object);
    }

    public function testInsertMultipleDataAtOnceInInheritance()
    {
        $object = new TDBMObject();
        $object->setProperty('login', 'jane.doe', 'users');
        $object->setProperty('name', 'Jane Doe', 'person');
        $object->setProperty('country_id', 1, 'users');
        $object->setProperty('email', 'jane@doe.com', 'contact');

        $this->tdbmService->save($object);

        $this->assertNotEmpty($object->getProperty('id', 'person'));
        $this->assertNotEmpty($object->getProperty('id', 'users'));
        $this->assertEquals($object->getProperty('id', 'person'), $object->getProperty('id', 'users'));
    }

    public function testCompleteSave()
    {
        $beans = $this->tdbmService->findObjects('users', 'users.login = :login', ['login' => 'jane.doe'], null, [], null, TDBMObject::class);
        $jane = $beans[0];
        $jane->setProperty('country_id', 2, 'users');

        $this->tdbmService->completeSave();
        $this->assertNotNull($jane->getProperty('id', 'users'));
    }

    public function testCompleteSave2()
    {
        $beans = $this->tdbmService->findObjects('users', 'users.login = :login', ['login' => 'jane.doe'], null, [], null, TDBMObject::class);
        $jane = $beans[0];

        $this->assertEquals(2, $jane->getProperty('country_id', 'users'));
    }

    public function testUpdatePrimaryKey()
    {
        $object = new TDBMObject('rights');
        $object->setProperty('label', 'CAN_EDIT_BOUK');

        $this->tdbmService->save($object);

        $object->setProperty('label', 'CAN_EDIT_BOOK');

        $this->tdbmService->save($object);

        $this->assertSame('CAN_EDIT_BOOK', $object->getProperty('label'));
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMInvalidOperationException
     *
     * @throws TDBMInvalidOperationException
     */
    public function testCannotDeleteDetachedObjects()
    {
        $object = new TDBMObject('rights');
        $object->setProperty('label', 'CAN_DELETE');

        $this->tdbmService->delete($object);
    }

    public function testDeleteNewObject()
    {
        $object = new TDBMObject('rights');
        $object->setProperty('label', 'CAN_DELETE');

        $this->tdbmService->attach($object);

        $this->tdbmService->delete($object);

        $exceptionRaised = false;
        try {
            $this->tdbmService->save($object);
        } catch (TDBMInvalidOperationException $e) {
            $exceptionRaised = true;
        }
        $this->assertTrue($exceptionRaised);
    }

    public function testDeleteLoadedObject()
    {
        $object = new TDBMObject('rights');
        $object->setProperty('label', 'CAN_DELETE');

        $this->tdbmService->save($object);

        $object->setProperty('label', 'CAN_DELETE2');

        $this->tdbmService->delete($object);

        // Try to delete a deleted object (this should do nothing)
        $this->tdbmService->delete($object);
        $this->assertSame(TDBMObjectStateEnum::STATE_DELETED, $object->_getStatus());
    }

    public function testFindObjects()
    {
        /*$magicQuery = new MagicQuery($this->tdbmService->getConnection());
        $result = $magicQuery->parse("SELECT DISTINCT users.id, users.login FROM users");
        var_dump($result);*/

        $beans = $this->tdbmService->findObjects('contact', null, [], 'contact.id ASC', [], null, TDBMObject::class);
        $beans2 = $this->tdbmService->findObjects('contact', 'contact.id = :id', ['id' => 1], null, [], null, TDBMObject::class);

        foreach ($beans as $bean) {
            $bean1 = $bean;
            break;
        }

        foreach ($beans2 as $bean) {
            $bean2 = $bean;
            break;
        }

        $this->assertTrue($bean1 === $bean2);
        $this->assertEquals(5, $beans->count());
        $this->assertEquals(1, $beans2->count());

        //$this->assertTrue($beans[0] === $beans2[0]);
        //var_dump($beans);
    }

    public function testRawSqlFilterCountriesByUserCount()
    {
        $this->onlyMySql();

        $sql = <<<SQL
SELECT country.*, GROUP_CONCAT(users.id) AS ids
FROM country
JOIN users ON country.id= users.country_id
GROUP BY country.id
HAVING COUNT(users.id) > 1;
SQL;
        /** @var Test\Dao\Bean\CountryBean[]|\Porpaginas\Result $beans */
        $beans = $this->tdbmService->findObjectsFromRawSql('country', $sql, [], null, Test\Dao\Bean\CountryBean::class);

        $count = 0;
        foreach ($beans as $country) {
            $this->assertGreaterThan(1, count($country->getUsers()));
            $count++;
        }
        $this->assertEquals($count, $beans->count());
    }

    public function testRawSqlOrderCountriesByUserCount()
    {
        $this->onlyMySql();

        $sql = <<<SQL
SELECT country.*, GROUP_CONCAT(users.id) AS ids
FROM country
JOIN users ON country.id= users.country_id
GROUP BY country.id
ORDER BY COUNT(users.id);
SQL;

        /** @var Test\Dao\Bean\CountryBean[]|\Porpaginas\Result $beans */
        $beans = $this->tdbmService->findObjectsFromRawSql('country', $sql, [], null, Test\Dao\Bean\CountryBean::class);

        $count = 0;
        foreach ($beans as $country) {
            $count++;
        }
        $this->assertEquals($count, $beans->count());

        for ($i = 1; $i < count($beans); $i++) {
            $this->assertLessThanOrEqual(count($beans[$i]->getUsers()), count($beans[$i - 1]->getUsers()));
        }
    }

    public function testRawSqlOrderUsersByCustomRoleOrder()
    {
        $this->onlyMySql();

        $sql = <<<SQL
SELECT `person`.*, `contact`.*, `users`.*
FROM `contact`
JOIN `users` ON `users`.`id` = `contact`.`id`
JOIN `person` ON `person`.`id` = `users`.`id`
JOIN `users_roles` ON users.id = users_roles.user_id
JOIN `roles` ON roles.id = users_roles.role_id
GROUP BY users.id
ORDER BY MAX(IF(roles.name = 'Admins', 3, IF(roles.name = 'Writers', 2, IF(roles.name = 'Singers', 1, 0)))) DESC
SQL;

        /** @var Test\Dao\Bean\UserBean[]|\Porpaginas\Result $beans */
        $beans = $this->tdbmService->findObjectsFromRawSql('contact', $sql, [], null, Test\Dao\Bean\UserBean::class);

        function getCustomOrder(Test\Dao\Bean\UserBean $contact)
        {
            $max = 0;
            foreach ($contact->getRoles() as $role) {
                $max = max($max, [
                    'Admins' => 3,
                    'Writers' => 2,
                    'Singers' => 1,
                ][$role->getName()]);
            }
            return $max;
        }

        $this->assertCount(4, $beans);

        for ($i = 1; $i < count($beans); $i++) {
            $this->assertGreaterThanOrEqual(getCustomOrder($beans[$i]), getCustomOrder($beans[$i - 1]), 'Beans order does not comply with expected result.');
        }
    }

    public function testArrayAccess()
    {
        $beans = $this->tdbmService->findObjects('contact', null, [], 'contact.id ASC', [], null, TDBMObject::class);

        $this->assertTrue(isset($beans[0]));
        $this->assertFalse(isset($beans[42]));
        $this->assertEquals(1, $beans[0]->getProperty('id', 'person'));

        $result1 = [];
        foreach ($beans as $bean) {
            $result1[] = $bean;
        }

        $result2 = [];
        foreach ($beans as $bean) {
            $result2[] = $bean;
        }

        $this->assertEquals($result1, $result2);
        $this->assertTrue($result1[0] === $result2[0]);
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMInvalidOffsetException
     *
     * @throws TDBMInvalidOffsetException
     */
    public function testArrayAccessException()
    {
        $beans = $this->tdbmService->findObjects('contact', null, [], 'contact.id ASC', [], null, TDBMObject::class);

        $beans[-1];
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMInvalidOffsetException
     *
     * @throws TDBMInvalidOffsetException
     */
    public function testArrayAccessException2()
    {
        $beans = $this->tdbmService->findObjects('contact', null, [], 'contact.id ASC', [], null, TDBMObject::class);

        $beans['foo'];
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     */
    public function testBeanGetException()
    {
        $beans = $this->tdbmService->findObjects('contact', null, [], 'contact.id ASC', [], null, TDBMObject::class);
        $bean = $beans[0];

        // we don't specify the table on inheritance table => exception.
        $bean->getProperty('id');
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     */
    public function testBeanSetException()
    {
        $beans = $this->tdbmService->findObjects('contact', null, [], 'contact.id ASC', [], null, TDBMObject::class);
        $bean = $beans[0];

        // we don't specify the table on inheritance table => exception.
        $bean->setProperty('name', 'foo');
    }

    public function testTake()
    {
        $beans = $this->tdbmService->findObjects('contact', null, [], 'contact.id ASC', [], null, TDBMObject::class);

        $page = $beans->take(0, 2);

        $this->assertEquals(2, $page->count());

        $results = [];
        foreach ($page as $result) {
            $results[] = $result;
        }
        $this->assertCount(2, $results);

        $this->assertEquals(5, $page->totalCount());

        $page = $beans->take(1, 1);

        $this->assertEquals(1, $page->count());

        $resultArray = $page->toArray();
        $this->assertCount(1, $resultArray);
        $this->assertTrue($resultArray[0] === $page[0]);
        // Test page isset
        $this->assertTrue(isset($page[0]));
    }

    public function testTakeInCursorMode()
    {
        $beans = $this->tdbmService->findObjects('contact', null, [], 'contact.id ASC', [], TDBMService::MODE_CURSOR, TDBMObject::class);

        $page = $beans->take(0, 2);

        $this->assertEquals(2, $page->count());
        $this->assertEquals(0, $page->getCurrentOffset());
        $this->assertEquals(2, $page->getCurrentLimit());
        $this->assertEquals(1, $page->getCurrentPage());

        $results = [];
        foreach ($page as $result) {
            $results[] = $result;
        }
        $this->assertCount(2, $results);

        $this->assertEquals(5, $page->totalCount());

        $page = $beans->take(1, 1);
        $this->assertEquals(1, $page->getCurrentOffset());
        $this->assertEquals(1, $page->getCurrentLimit());
        $this->assertEquals(2, $page->getCurrentPage());

        $this->assertEquals(1, $page->count());
    }

    public function testMap()
    {
        $beans = $this->tdbmService->findObjects('person', null, [], 'person.id ASC', [], null, TDBMObject::class);

        $results = $beans->map(function ($item) {
            return $item->getProperty('id', 'person');
        })->toArray();

        $this->assertEquals([1, 2, 3, 4, 6], $results);

        // Same test with page
        $page = $beans->take(0, 2);

        $results = $page->map(function ($item) {
            return $item->getProperty('id', 'person');
        })->toArray();

        $this->assertEquals([1, 2], $results);
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     */
    public function testUnsetException()
    {
        $beans = $this->tdbmService->findObjects('contact', null, [], 'contact.id ASC', [], null, TDBMObject::class);

        unset($beans[0]);
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     */
    public function testSetException()
    {
        $beans = $this->tdbmService->findObjects('contact', null, [], 'contact.id ASC', [], null, TDBMObject::class);

        $beans[0] = 'foo';
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     */
    public function testPageUnsetException()
    {
        $beans = $this->tdbmService->findObjects('contact', null, [], 'contact.id ASC', [], null, TDBMObject::class);
        $page = $beans->take(0, 1);
        unset($page[0]);
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     */
    public function testPageSetException()
    {
        $beans = $this->tdbmService->findObjects('contact', null, [], 'contact.id ASC', [], null, TDBMObject::class);
        $page = $beans->take(0, 1);
        $page[0] = 'foo';
    }

    public function testToArray()
    {
        $beans = $this->tdbmService->findObjects('contact', 'contact.id = :id', ['id' => 1], null, [], null, TDBMObject::class);

        $beanArray = $beans->toArray();

        $this->assertCount(1, $beanArray);
        $this->assertEquals(1, $beanArray[0]->getProperty('id', 'contact'));
    }

    public function testCursorMode()
    {
        $beans = $this->tdbmService->findObjects('contact', 'contact.id = :id', ['id' => 1], null, [], TDBMService::MODE_CURSOR, TDBMObject::class);

        $this->assertInstanceOf('\\TheCodingMachine\\TDBM\\ResultIterator', $beans);

        $result = [];
        foreach ($beans as $bean) {
            $result[] = $bean;
        }

        $this->assertCount(1, $result);

        // In cursor mode, access by array causes an exception.
        $exceptionTriggered = false;
        try {
            $beans[0];
        } catch (TDBMInvalidOperationException $e) {
            $exceptionTriggered = true;
        }
        $this->assertTrue($exceptionTriggered);

        $exceptionTriggered = false;
        try {
            isset($beans[0]);
        } catch (TDBMInvalidOperationException $e) {
            $exceptionTriggered = true;
        }
        $this->assertTrue($exceptionTriggered);
    }

    public function testSetFetchMode()
    {
        $this->tdbmService->setFetchMode(TDBMService::MODE_CURSOR);
        $beans = $this->tdbmService->findObjects('contact', 'contact.id = :id', ['id' => 1], null, [], null, TDBMObject::class);

        $this->assertInstanceOf('\\TheCodingMachine\\TDBM\\ResultIterator', $beans);

        // In cursor mode, access by array causes an exception.
        $exceptionTriggered = false;
        try {
            $beans[0];
        } catch (TDBMInvalidOperationException $e) {
            $exceptionTriggered = true;
        }
        $this->assertTrue($exceptionTriggered);
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     */
    public function testInvalidSetFetchMode()
    {
        $this->tdbmService->setFetchMode(99);
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     */
    public function testCursorModeException()
    {
        $beans = $this->tdbmService->findObjects('contact', 'contact.id = :id', ['id' => 1], null, [], 99);
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     */
    public function testTableNameException()
    {
        $beans = $this->tdbmService->findObjects('foo bar');
    }

    public function testLinkedTableFetch()
    {
        $beans = $this->tdbmService->findObjects('contact', 'contact.id = :id', ['id' => 1], null, ['country'], null, TDBMObject::class);
        $this->assertInstanceOf(ResultIterator::class, $beans);
    }

    public function testFindObject()
    {
        $bean = $this->tdbmService->findObject('contact', 'contact.id = :id', ['id' => -42], [], TDBMObject::class);
        $this->assertNull($bean);
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\NoBeanFoundException
     *
     * @throws NoBeanFoundException
     */
    public function testFindObjectOrFail()
    {
        $bean = $this->tdbmService->findObjectOrFail('contact', 'contact.id = :id', ['id' => -42], [], TDBMObject::class);
    }

    /**
     * @throws NoBeanFoundException
     */
    public function testFindObjectByPkException()
    {
        $this->expectException(NoBeanFoundException::class);
        $this->expectExceptionMessage("No result found for query on table 'contact' for 'id' = -42");
        $bean = $this->tdbmService->findObjectByPk('contact', ['id' => -42], [], false, TDBMObject::class);
    }

    /**
     * @throws DuplicateRowException
     */
    public function testFindObjectDuplicateRow()
    {
        $this->expectException(DuplicateRowException::class);

        $bean = $this->tdbmService->findObject('contact');
    }

    public function testFindObjectsByBean()
    {
        $countryBean = $this->tdbmService->findObject('country', 'id = :id', ['id' => 1], [], TDBMObject::class);

        $users = $this->tdbmService->findObjects('users', $countryBean, [], null, [], null, TDBMObject::class);
        $this->assertCount(1, $users);
        $this->assertEquals('jean.dupont', $users[0]->getProperty('login', 'users'));
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     * @throws TDBMInvalidOperationException
     */
    public function testBeanWithoutStatus()
    {
        $reflectionClass = new \ReflectionClass(TDBMObject::class);
        $object = $reflectionClass->newInstanceWithoutConstructor();
        $object->_getStatus();
    }

    public function testFindObjectsFromSql()
    {
        $roles = $this->tdbmService->findObjectsFromSql(
            'roles',
            'roles JOIN roles_rights ON roles.id = roles_rights.role_id JOIN rights ON rights.label = roles_rights.right_label',
            'rights.label = :right',
            array('right' => 'CAN_SING'),
            'roles.name DESC'
        );
        $this->assertCount(2, $roles);
        $this->assertInstanceOf(AbstractTDBMObject::class, $roles[0]);
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     */
    public function testFindObjectsFromSqlBadTableName()
    {
        $this->tdbmService->findObjectsFromSql(
            '#{azerty',
            'roles JOIN roles_rights ON roles.id = roles_rights.role_id JOIN rights ON rights.label = roles_rights.right_label',
            'rights.label = :right',
            array('right' => 'CAN_SING'),
            'name DESC'
        );
    }

    /**
     * @expectedException \TheCodingMachine\TDBM\TDBMException
     *
     * @throws TDBMException
     */
    public function testFindObjectsFromSqlGroupBy()
    {
        $roles = $this->tdbmService->findObjectsFromSql(
            'roles',
            'roles JOIN roles_rights ON roles.id = roles_rights.role_id JOIN rights ON rights.label = roles_rights.right_label',
            'rights.label = :right GROUP BY roles.name',
            array('right' => 'CAN_SING'),
            'name DESC'
        );
        $role = $roles[0];
    }

    public function testFindObjectFromSql()
    {
        $role = $this->tdbmService->findObjectFromSql(
            'roles',
            'roles JOIN roles_rights ON roles.id = roles_rights.role_id JOIN rights ON rights.label = roles_rights.right_label',
            'rights.label = :right AND name = :name',
            array('right' => 'CAN_SING', 'name' => 'Singers')
        );
        $this->assertInstanceOf(AbstractTDBMObject::class, $role);
    }

    /**
     * @throws DuplicateRowException
     */
    public function testFindObjectFromSqlException()
    {
        $this->expectException(DuplicateRowException::class);
        $this->expectExceptionMessage('Error while querying an object in table \'roles\': More than 1 row have been returned, but we should have received at most one for filter "rights.label = \'CAN_SING\'".');
        $this->tdbmService->findObjectFromSql(
            'roles',
            'roles JOIN roles_rights ON roles.id = roles_rights.role_id JOIN rights ON rights.label = roles_rights.right_label',
            'rights.label = :right',
            array('right' => 'CAN_SING')
        );
    }

    public function testFindObjectsFromSqlHierarchyDown()
    {
        $users = $this->tdbmService->findObjectsFromSql(
            'person',
            'person',
            'name LIKE :name OR name LIKE :name2',
            array('name' => 'Robert Marley', 'name2' => 'Bill Shakespeare'),
            null,
            null,
            TDBMObject::class
        );
        $this->assertCount(2, $users);
        $this->assertSame('robert.marley', $users[0]->getProperty('login', 'users'));
    }

    public function testFindObjectsFromSqlHierarchyUp()
    {
        $users = $this->tdbmService->findObjectsFromSql(
            'users',
            'users',
            'login LIKE :login OR login LIKE :login2',
            array('login' => 'robert.marley', 'login2' => 'bill.shakespeare'),
            'users.login DESC',
            null,
            TDBMObject::class
        );
        $this->assertCount(2, $users);
        $this->assertSame('Robert Marley', $users[0]->getProperty('name', 'person'));
    }

    public function testLogger()
    {
        $arrayLogger = new ArrayLogger();
        $tdbmService = new TDBMService(new Configuration('TheCodingMachine\\TDBM\\Test\\Dao\\Bean', 'TheCodingMachine\\TDBM\\Test\\Dao', self::getConnection(), $this->getNamingStrategy(), null, null, $arrayLogger));

        $tdbmService->setLogLevel(LogLevel::DEBUG);
        $beans = $tdbmService->findObjects('contact', null, [], 'contact.id ASC', [], null, TDBMObject::class);
        $beans->first();

        $this->assertNotEmpty($arrayLogger->get());
    }

    public function testFindObjectsCountWithOneToManyLink()
    {
        $countries = $this->tdbmService->findObjects('country', "users.status = 'on' OR users.status = 'off'");

        $this->assertEquals(3, $countries->count());
    }

    public function testFindObjectsFromSqlCountWithOneToManyLink()
    {
        $countries = $this->tdbmService->findObjectsFromSql('country', 'country LEFT JOIN users ON country.id = users.country_id', "users.status = 'on' OR users.status = 'off'");

        $this->assertEquals(3, $countries->count());
    }
}
