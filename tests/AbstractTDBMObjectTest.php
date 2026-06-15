<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM;

use PHPUnit\Framework\TestCase;

use function foo\func;

class AbstractTDBMObjectTest extends TestCase
{
    public function testGetManyToManyRelationshipDescriptor(): void
    {
        $object = new TDBMObject();
        $this->expectException(TDBMException::class);
        $this->expectExceptionMessage('Could not find many to many relationship descriptor key for "foo"');
        $object->_getManyToManyRelationshipDescriptor('foo');
    }

    public function testEmptyResultIterator(): void
    {
        $a = ResultIterator::createEmpyIterator();
        foreach ($a as $empty) {
            throw new \LogicException("Not supposed to iterate on an empty iterator.");
        }
        $this->assertEquals(0, $a->count());
        $this->assertEquals(null, $a->first());
        $this->assertEquals(null, isset($a[0])); //an empty resultIterator must implement arrayAccess
        $this->assertEquals([], $a->toArray());
        foreach ($a->map(function ($foo): void {}) as $empty) {
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

    public function testEmptyPageIterator(): void
    {
        $a = EmptyResultIterator::createEmpyIterator();
        $b = $a->take(0, 10);
        foreach ($b as $empty) {
            throw new \LogicException("Not supposed to iterate on an empty page iterator.");
        }
        $this->assertEquals(0, $b->count());
        $this->assertEquals([], $b->toArray());
        $this->assertEquals(0, $b->totalCount());
        $c = $b->map(function ($foo): void {});
        foreach ($c as $empty) {
            throw new \LogicException("Not supposed to iterate on an empty iterator.");
        }
        $this->assertEquals([], $c->toArray());
    }
}
