<?php

namespace TheCodingMachine\TDBM\Iterators;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use function iterator_to_array;
use function range;

class CachingIteratorTest extends TestCase
{
    public function testCachingIterator()
    {
        $it = new CachingIterator(new ArrayIterator(range(1,5)));

        $it[3];
        $this->assertArrayHasKey(3, $it);
        $this->assertCount(5, $it);
        $arr = iterator_to_array($it);
        $this->assertSame([1, 2, 3, 4, 5], $arr);
    }

    public function testCachingIterator2()
    {
        $it = new CachingIterator(new ArrayIterator(range(1,5)));

        $arr = iterator_to_array($it);
        $this->assertSame([1, 2, 3, 4, 5], $arr);
    }
}
