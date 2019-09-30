<?php

namespace TheCodingMachine\TDBM;

use PHPUnit\Framework\TestCase;

class EmptyInnerResultIteratorTest extends TestCase
{
    public function testOffsetUnset()
    {
        $iterator = new EmptyInnerResultIterator();
        $this->expectException(TDBMInvalidOperationException::class);
        unset($iterator[42]);
    }

    public function testCount()
    {
        $iterator = new EmptyInnerResultIterator();
        $this->assertCount(0, $iterator);
    }

    public function testOffsetExists()
    {
        $iterator = new EmptyInnerResultIterator();
        $this->assertFalse(isset($iterator[0]));
    }

    public function testOffsetSet()
    {
        $iterator = new EmptyInnerResultIterator();
        $this->expectException(TDBMInvalidOperationException::class);
        $iterator[42] = 'foo';
    }

    public function testIterate()
    {
        $iterator = new EmptyInnerResultIterator();
        foreach ($iterator as $elem) {
            $this->fail('Iterator should be empty');
        }
        $this->assertTrue(true);
    }

    public function testOffsetGet()
    {
        $iterator = new EmptyInnerResultIterator();
        $this->expectException(TDBMInvalidOffsetException::class);
        $iterator[42];
    }
}
