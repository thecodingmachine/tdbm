<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM;

use PHPUnit\Framework\TestCase;

class EmptyInnerResultIteratorTest extends TestCase
{
    public function testOffsetUnset(): void
    {
        $iterator = new EmptyInnerResultIterator();
        $this->expectException(TDBMInvalidOperationException::class);
        unset($iterator[42]);
    }

    public function testCount(): void
    {
        $iterator = new EmptyInnerResultIterator();
        $this->assertCount(0, $iterator);
    }

    public function testOffsetExists(): void
    {
        $iterator = new EmptyInnerResultIterator();
        $this->assertFalse(isset($iterator[0]));
    }

    public function testOffsetSet(): void
    {
        $iterator = new EmptyInnerResultIterator();
        $this->expectException(TDBMInvalidOperationException::class);
        $iterator[42] = 'foo';
    }

    public function testIterate(): void
    {
        $iterator = new EmptyInnerResultIterator();
        foreach ($iterator as $elem) {
            $this->fail('Iterator should be empty');
        }

        $iterator->next();
        $this->assertNull($iterator->current());
        $this->assertNull($iterator->key());
    }

    public function testOffsetGet(): void
    {
        $iterator = new EmptyInnerResultIterator();
        $this->expectException(TDBMInvalidOffsetException::class);
        $iterator[42];
    }
}
