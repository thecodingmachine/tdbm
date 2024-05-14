<?php

namespace TheCodingMachine\TDBM;

use PHPUnit\Framework\TestCase;

use function foo\func;

class AbstractTDBMObjectTest extends TestCase
{
    public function testGetManyToManyRelationshipDescriptor()
    {
        $object = new TDBMObject();
        $this->expectException(TDBMException::class);
        $this->expectExceptionMessage('Could not find many to many relationship descriptor key for "foo"');
        $object->_getManyToManyRelationshipDescriptor('foo');
    }

    public function testEmptyResultIterator()
    {
        $a = ResultIterator::createEmpyIterator();
        foreach ($a as $empty) {
            throw new \LogicException("Not supposed to iterate on an empty iterator.");
        }
        $this->assertEquals(0, $a->count());
        $this->assertEquals(null, $a->first());
        $this->assertEquals(null, isset($a[0])); //an empty resultIterator must implement arrayAccess
        $this->assertEquals([], $a->toArray());
        foreach ($a->map(function ($foo) {
        }) as $empty) {
            throw new \LogicException("Not supposed to iterate on an empty iterator.");
        }
        $c = $a->withOrder("who cares");
        foreach ($c as $empty) {
            throw new \LogicException("Not supposed to iterate on an empty iterator.");
        }
        $this->assertEquals(0, $c->count());
        $d = $a->withParameters(["who cares"]);
        foreach ($d as $empty) {
            throw new \LogicException("Not supposed to iterate on an empty iterator.");
        }
        $this->assertEquals(0, $d->count());
    }

}
