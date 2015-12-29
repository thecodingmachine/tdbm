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

use Mouf\Utils\Cache\NoCache;
use Mouf\Database\TDBM\Filters\EqualFilter;
use Mouf\Database\TDBM\Filters\OrderByColumn;

/**
 */
class TDBMServiceTest extends TDBMAbstractServiceTest {

    public function testGetLinkBetweenInheritedTables() {
        $this->assertEquals(['users', 'contact', 'person'], $this->tdbmService->_getLinkBetweenInheritedTables(["contact", "users"]));
        $this->assertEquals(['users', 'contact', 'person'], $this->tdbmService->_getLinkBetweenInheritedTables(["users", "contact"]));
        $this->assertEquals(['contact', 'person'], $this->tdbmService->_getLinkBetweenInheritedTables(["person", "contact"]));
        $this->assertEquals(['users', 'contact', 'person'], $this->tdbmService->_getLinkBetweenInheritedTables(["users"]));
        $this->assertEquals(['person'], $this->tdbmService->_getLinkBetweenInheritedTables(["person"]));
    }

    public function testGetRelatedTablesByInheritance() {
        $contactRelatedTables = $this->tdbmService->_getRelatedTablesByInheritance('contact');
        $this->assertCount(3, $contactRelatedTables);
        $this->assertContains("users", $contactRelatedTables);
        $this->assertContains("contact", $contactRelatedTables);
        $this->assertContains("person", $contactRelatedTables);
        $this->assertCount(3, $this->tdbmService->_getRelatedTablesByInheritance('users'));
        $this->assertCount(3, $this->tdbmService->_getRelatedTablesByInheritance('person'));

    }

    /**
     * @expectedException \Mouf\Database\TDBM\TDBMException
     * @throws TDBMException
     */
    public function testGetPrimaryKeysFromIndexedPrimaryKeysException() {
        $this->tdbmService->_getPrimaryKeysFromIndexedPrimaryKeys("users", [5, 4]);
    }

    /**
     * @expectedException \Mouf\Database\TDBM\TDBMException
     * @throws TDBMException
     */
    public function testGetLinkBetweenInheritedTablesExceptions() {
        $this->tdbmService->_getLinkBetweenInheritedTables(["contact", "country"]);
    }

    public function testHashPrimaryKey() {
		$reflection = new \ReflectionClass(get_class($this->tdbmService));
		$method = $reflection->getMethod('getObjectHash');
		$method->setAccessible(true);

		$result = $method->invokeArgs($this->tdbmService, [
			[ 'id' => 42 ]
		]);
		$this->assertEquals(42, $result);

		// Check that multiple primary keys are insensitive to column order
		$result1 = $method->invokeArgs($this->tdbmService, [
			[ 'id1' => 42, 'id2' => 24 ]
		]);
		$result2 = $method->invokeArgs($this->tdbmService, [
			[ 'id2' => 24, 'id1' => 42 ]
		]);
		$this->assertEquals($result1, $result2);
	}

	public function testInsertAndUpdateAndDelete() {
		$object = new TDBMObject("users");
		$object->login = "john.doe";
		$object->country_id = 3;

		$this->tdbmService->save($object);

		$this->assertNotEmpty($object->get('id', 'person'));
        $this->assertNotEmpty($object->get('id', 'users'));
        $this->assertEquals($object->get('id', 'person'), $object->get('id', 'users'));

		$object->set('country_id', 2, 'users');

		$this->tdbmService->save($object);

        $this->tdbmService->delete($object);
	}

    public function testInsertMultipleDataAtOnceInInheritance() {
        $object = new TDBMObject();
        $object->set('login', 'jane.doe', 'users');
        $object->set('name', 'Jane Doe', 'person');
        $object->set('country_id', 2, 'users');

        $this->tdbmService->save($object);

        $this->assertNotEmpty($object->get('id', 'person'));
        $this->assertNotEmpty($object->get('id', 'users'));
        $this->assertEquals($object->get('id', 'person'), $object->get('id', 'users'));
    }


    public function testUpdatePrimaryKey() {
		$object = new TDBMObject("rights");
		$object->label = "CAN_EDIT_BOUK";


		$this->tdbmService->save($object);

		$object->label = "CAN_EDIT_BOOK";

		$this->tdbmService->save($object);
	}

    /**
     * @expectedException \Mouf\Database\TDBM\TDBMInvalidOperationException
     * @throws TDBMInvalidOperationException
     */
    public function testCannotDeleteDetachedObjects() {
        $object = new TDBMObject("rights");
        $object->label = "CAN_DELETE";

        $this->tdbmService->delete($object);
    }


    public function testDeleteNewObject() {
        $object = new TDBMObject("rights");
        $object->label = "CAN_DELETE";

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

    public function testDeleteLoadedObject() {
        $object = new TDBMObject("rights");
        $object->label = "CAN_DELETE";

        $this->tdbmService->save($object);

        $object->label = "CAN_DELETE2";

        $this->tdbmService->delete($object);

        // Try to delete a deleted object (this should do nothing)
        $this->tdbmService->delete($object);
    }

    public function testFindObjects() {
        /*$magicQuery = new MagicQuery($this->tdbmService->getConnection());
        $result = $magicQuery->parse("SELECT DISTINCT users.id, users.login FROM users");
        var_dump($result);*/

        $beans = $this->tdbmService->findObjects("contact", null, [], "contact.id ASC");
        $beans2 = $this->tdbmService->findObjects("contact", "contact.id = :id", ["id"=>1]);

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

    public function testArrayAccess()
    {
        $beans = $this->tdbmService->findObjects("contact", null, [], "contact.id ASC");

        $this->assertTrue(isset($beans[0]));
        $this->assertFalse(isset($beans[42]));
        $this->assertEquals(1, $beans[0]->get('id', 'person'));

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
     * @expectedException \Mouf\Database\TDBM\TDBMInvalidOffsetException
     * @throws TDBMInvalidOffsetException
     */
    public function testArrayAccessException()
    {
        $beans = $this->tdbmService->findObjects("contact", null, [], "contact.id ASC");

        $beans[-1];
    }

    /**
     * @expectedException \Mouf\Database\TDBM\TDBMInvalidOffsetException
     * @throws TDBMInvalidOffsetException
     */
    public function testArrayAccessException2()
    {
        $beans = $this->tdbmService->findObjects("contact", null, [], "contact.id ASC");

        $beans["foo"];
    }




    public function testTake() {
        $beans = $this->tdbmService->findObjects("contact", null, [], "contact.id ASC");

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

    public function testTakeInCursorMode() {
        $beans = $this->tdbmService->findObjects("contact", null, [], "contact.id ASC", [], TDBMService::MODE_CURSOR);

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

    public function testMap() {
        $beans = $this->tdbmService->findObjects("person", null, [], "person.id ASC");

        $results = $beans->map(function($item) {
           return $item->get('id', 'person');
        })->toArray();

        $this->assertEquals([1,2,3,4,6], $results);

        // Same test with page
        $page = $beans->take(0, 2);

        $results = $page->map(function($item) {
            return $item->get('id', 'person');
        })->toArray();

        $this->assertEquals([1,2], $results);

    }

    /**
     * @expectedException \Mouf\Database\TDBM\TDBMException
     * @throws TDBMException
     */
    public function testUnsetException() {
        $beans = $this->tdbmService->findObjects("contact", null, [], "contact.id ASC");

        unset($beans[0]);
    }

    /**
     * @expectedException \Mouf\Database\TDBM\TDBMException
     * @throws TDBMException
     */
    public function testSetException() {
        $beans = $this->tdbmService->findObjects("contact", null, [], "contact.id ASC");

        $beans[0] = "foo";
    }

    /**
     * @expectedException \Mouf\Database\TDBM\TDBMException
     * @throws TDBMException
     */
    public function testPageUnsetException() {
        $beans = $this->tdbmService->findObjects("contact", null, [], "contact.id ASC");
        $page = $beans->take(0,1);
        unset($page[0]);
    }

    /**
     * @expectedException \Mouf\Database\TDBM\TDBMException
     * @throws TDBMException
     */
    public function testPageSetException() {
        $beans = $this->tdbmService->findObjects("contact", null, [], "contact.id ASC");
        $page = $beans->take(0,1);
        $page[0] = "foo";
    }


    public function testToArray() {
        $beans = $this->tdbmService->findObjects("contact", "contact.id = :id", ["id"=>1]);

        $beanArray = $beans->toArray();

        $this->assertCount(1, $beanArray);
        $this->assertEquals(1, $beanArray[0]->get('id', 'contact'));
    }

    public function testCursorMode() {
        $beans = $this->tdbmService->findObjects("contact", "contact.id = :id", ["id"=>1], null, [], TDBMService::MODE_CURSOR);

        $this->assertInstanceOf("\\Mouf\\Database\\TDBM\\ResultIterator", $beans);

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

    /**
     * @expectedException \Mouf\Database\TDBM\TDBMException
     * @throws TDBMException
     */
    public function testCursorModeException() {
        $beans = $this->tdbmService->findObjects("contact", "contact.id = :id", ["id"=>1], null, [], "foobaz");
    }

    /**
     * @expectedException \Mouf\Database\TDBM\TDBMException
     * @throws TDBMException
     */
    public function testTableNameException() {
        $beans = $this->tdbmService->findObjects("foo bar");
    }

    public function testLinkedTableFetch() {
        $beans = $this->tdbmService->findObjects("contact", "contact.id = :id", ["id"=>1], null, ['country']);
    }

    public function testFindObject() {
        $bean = $this->tdbmService->findObject("contact", "contact.id = :id", ["id"=>-42]);
        $this->assertNull($bean);
    }

    /**
     * @expectedException \Mouf\Database\TDBM\NoBeanFoundException
     * @throws NoBeanFoundException
     */
    public function testFindObjectOrFail() {
        $bean = $this->tdbmService->findObjectOrFail("contact", "contact.id = :id", ["id"=>-42]);
    }

    /**
     * @expectedException \Mouf\Database\TDBM\DuplicateRowException
     * @throws DuplicateRowException
     */
    public function testFindObjectDuplicateRow() {
        $bean = $this->tdbmService->findObject("contact");
    }

    public function testFindObjectsByBean() {
        $countryBean = $this->tdbmService->findObject("country", "id = :id", ["id"=>1]);

        $users = $this->tdbmService->findObjects("users", $countryBean);
        $this->assertCount(1, $users);
        $this->assertEquals("jean.dupont", $users[0]->get('login', 'users'));
    }


    /*
        public function testObjectAsFilter() {
            $dpt = $this->tdbmService->getObject('departements', 1);
            $dpt2 =  $this->tdbmService->getObject('departements', $dpt);
            $this->assertEquals($dpt, $dpt2);
        }

        public function testOneWayAndTheOpposite() {
            $this->tdbmService->getObjects('utilisateur_entite', new EqualFilter('entites', 'appellation', 'foo'));
            $this->tdbmService->getObjects('entites', new EqualFilter('utilisateur_entite', 'id_utilisateur', '1'));
        }

        public function testOneWayAndTheOpposite2() {
            $this->tdbmService->getObjects('utilisateur_entite', new EqualFilter('departements', 'id', '1'));
            $this->tdbmService->getObjects('departements', new EqualFilter('utilisateur_entite', 'id_utilisateur', '1'));
        }

        public function testOneWayAndTheOpposite3() {
            $this->tdbmService->getObjects('utilisateur_entite',
                    [
                    new EqualFilter('entites', 'appellation', 1),
                    ]
            );
            $this->tdbmService->getObjects('entites', [
                        new EqualFilter('departements', 'id', 1),
                        new EqualFilter('utilisateur_entite', 'id_utilisateur', '1'),
                    ]
            );
        }

        public function testOneWayAndTheOpposite4() {
            $this->tdbmService->getObjects('utilisateur_entite', null,
                    [
                    new OrderByColumn('entites', 'appellation', 'ASC'),
                    ]
            );
            $this->tdbmService->getObjects('entites', new EqualFilter('utilisateur_entite', 'id_utilisateur', '1'),
                    [
                    new OrderByColumn('departements', 'id', 'ASC')
                    ]
            );
        }

        public function testTDBMObjectArrayMultipleForeach() {
            $results = $this->tdbmService->getObjects('departements');
            $this->assertTrue(is_array($results));
            $count = 0;
            foreach ($results as $result) {
                $count++;
            }
            $this->assertEquals(95, $count);

            $count = 0;
            foreach ($results as $result) {
                $count++;
            }
            $this->assertEquals(95, $count);

        }

        public function testTDBMObjectsCursorMode() {
            $this->tdbmService->setFetchMode(TDBMService::MODE_CURSOR);
            $results = $this->tdbmService->getObjects('departements');

            $count = 0;
            foreach ($results as $result) {
                $count++;
            }
            $this->assertEquals(95, $count);
        }

        public function testTDBMObjectCursorMode() {
            $this->tdbmService->setFetchMode(TDBMService::MODE_CURSOR);
            $result = $this->tdbmService->getObject('departements', array(new EqualFilter('departements', 'id', 1)));

            $this->assertEquals("Ain", $result->nom);
        }


        public function testTDBMObjectArrayCount() {
            $results = $this->tdbmService->getObjects('departements');
            $this->assertEquals(95, count($results));
            $this->assertEquals(95, count($results));

        }


        public function testTDBMObjectArrayAccessByKey() {
            $results = $this->tdbmService->getObjects('departements');

            $this->assertEquals("Alpes Maritimes", $results[5]->nom);
        }

        public function testTDBMObjectArrayJsonEncode() {
            $this->tdbmService->setFetchMode(TDBMService::MODE_COMPATIBLE_ARRAY);
            $jsonEncoded = json_encode($this->tdbmService->getObjects('departements'));
            $count = count(json_decode($jsonEncoded));

            $this->assertEquals(95, $count);
        }

        public function testInnerJsonEncode() {
            $this->tdbmService->setFetchMode(TDBMService::MODE_COMPATIBLE_ARRAY);
            $departements = $this->tdbmService->getObjects('departements');
            $jsonEncoded = json_encode(['departements'=>$departements]);
            $count = count(json_decode($jsonEncoded, true)['departements']);

            $this->assertEquals(95, $count);
        }


        public function testCursorJsonEncode() {
            // COMMENTING THE WHOLE SCRIPT.
            // If we are in CURSOR mode, there is probably no point in json_encoding the result.
            /*$this->tdbmService->setFetchMode(TDBMService::MODE_CURSOR);
            $results = $this->tdbmService->getObjects('departements');
            $jsonEncoded = json_encode($results);
            $count = count(json_decode($jsonEncoded, true));

            $this->assertEquals(95, $count);
            * /
        }

        public function testTDBMObjectArrayCountAfterForeach() {
            $results = $this->tdbmService->getObjects('departements');
            foreach ($results as $result) {
                // Do nothing
            }
            $this->assertEquals(95, count($results));
        }

        public function testStorage() {
            $results = $this->tdbmService->getObjects('departements');

            $result = $this->tdbmService->getObject('departements', 1);

            $this->assertTrue($results[0] === $result);
        }

        public function testCloneTDBMObject()
        {
            // Create a new object
            $object = $this->tdbmService->getNewObject('departements');
            $object->id_region = 22;
            $object->numero = '100';
            $object->nom = 'test';
            $object->nom_web = 'test';
            // Save the object
            $object->save();

            // Try to clone the object
            $cloneObject = clone $object;
            // Save the cloned object
            $cloneObject->save();

            $this->assertNotEquals($object->id, $cloneObject->id);
            $this->assertEquals($object->nom, $cloneObject->nom);

            $this->tdbmService->deleteObject($object);
            $this->tdbmService->deleteObject($cloneObject);
        }
    */
}
