<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM;

use PHPUnit\Framework\TestCase;

class AlterableResultIteratorTest extends TestCase
{
    public function testUnalteredResultSet()
    {
        $a = (object) ['a' => 'a'];
        $b = (object) ['b' => 'c'];
        $c = (object) ['b' => 'c'];

        $iterator = new \ArrayIterator([$a, $b, $c]);

        $alterableResultIterator = new AlterableResultIterator($iterator);

        $this->assertEquals([$a, $b, $c], $alterableResultIterator->toArray());
        $this->assertEquals([$a, $b, $c], $alterableResultIterator->jsonSerialize());
        $this->assertEquals($a, $alterableResultIterator[0]);
        $this->assertTrue(isset($alterableResultIterator[0]));
        $this->assertCount(3, $alterableResultIterator);
        $this->assertEquals($a, $alterableResultIterator->first());
    }

    public function testEmptyResultSet()
    {
        $alterableResultIterator = new AlterableResultIterator();

        $this->assertEquals([], $alterableResultIterator->toArray());

        $this->assertEquals([], iterator_to_array($alterableResultIterator->getIterator()));
        $this->assertNull($alterableResultIterator->first());
    }

    public function testAlterEmptyResultSet()
    {
        $alterableResultIterator = new AlterableResultIterator();

        $a = (object) ['a' => 'a'];
        $b = (object) ['b' => 'c'];
        $c = (object) ['b' => 'c'];

        $alterableResultIterator->add($a);
        $alterableResultIterator->add($b);
        $alterableResultIterator->remove($b);
        $alterableResultIterator->remove($c);

        $this->assertEquals([$a], $alterableResultIterator->toArray());
    }

    public function testAlterFilledResultSet()
    {
        $a = (object) ['a' => 'a'];
        $b = (object) ['b' => 'c'];
        $c = (object) ['b' => 'c'];

        $iterator = new \ArrayIterator([$a, $b]);

        $alterableResultIterator = new AlterableResultIterator($iterator);

        $alterableResultIterator->add($c);
        $alterableResultIterator->remove($b);

        $this->assertEquals([$a, $c], $alterableResultIterator->toArray());
        $this->assertEquals([$c], iterator_to_array($alterableResultIterator->take(1, 1)));
    }

    public function testAddAfterToArray()
    {
        $a = (object) ['a' => 'a'];
        $b = (object) ['b' => 'c'];
        $c = (object) ['b' => 'c'];

        $iterator = new \ArrayIterator([$a, $b]);

        $alterableResultIterator = new AlterableResultIterator($iterator);

        $this->assertEquals([$a, $b], $alterableResultIterator->toArray());

        $alterableResultIterator->add($c);
        $alterableResultIterator->remove($b);

        $this->assertEquals([$a, $c], $alterableResultIterator->toArray());
    }

    public function testGetIterator()
    {
        $a = (object) ['a' => 'a'];
        $b = (object) ['b' => 'c'];
        $c = (object) ['b' => 'c'];

        $iterator = new \ArrayIterator([$a, $b]);

        $alterableResultIterator = new AlterableResultIterator($iterator);

        // Test getting the iterator with no alterations (should serve the initial iterator)
        $this->assertEquals([$a, $b], iterator_to_array($alterableResultIterator->getIterator()));
        $this->assertInstanceOf(\ArrayIterator::class, $alterableResultIterator->getIterator());

        $alterableResultIterator->add($c);
        $this->assertEquals([$a, $b, $c], iterator_to_array($alterableResultIterator->getIterator()));
        $this->assertInstanceOf(\ArrayIterator::class, $alterableResultIterator->getIterator());
    }

    public function testSetException()
    {
        $alterableResultIterator = new AlterableResultIterator();
        $this->expectException(TDBMInvalidOperationException::class);
        $alterableResultIterator[0] = 'foo';
    }

    public function testUnsetException()
    {
        $alterableResultIterator = new AlterableResultIterator();
        $this->expectException(TDBMInvalidOperationException::class);
        unset($alterableResultIterator[0]);
    }

    public function testMap()
    {
        $a = (object) ['foo' => 'bar'];

        $iterator = new \ArrayIterator([$a]);

        $alterableResultIterator = new AlterableResultIterator($iterator);

        $map = $alterableResultIterator->map(function ($item) {
            return $item->foo;
        });

        $this->assertEquals(['bar'], iterator_to_array($map));
    }

    public function testSetIterator()
    {
        $alterableResultIterator = new AlterableResultIterator();

        $a = (object) ['a' => 'a'];
        $b = (object) ['b' => 'c'];
        $c = (object) ['b' => 'c'];

        $iterator = new \ArrayIterator([$a, $b, $c]);

        $alterableResultIterator->setResultIterator($iterator);

        $this->assertEquals([$a, $b, $c], $alterableResultIterator->toArray());
    }
}
