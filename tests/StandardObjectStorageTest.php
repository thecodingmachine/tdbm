<?php

namespace TheCodingMachine\TDBM;

use PHPUnit\Framework\TestCase;

class StandardObjectStorageTest extends TestCase
{
    public function testObjectStorage(): void
    {
        $objectStorage = new StandardObjectStorage();
        $this->assertNull($objectStorage->get('foo', 42));
        $dbRow = $this->createMock(DbRow::class);
        $objectStorage->set('foo', 42, $dbRow);
        $this->assertSame($dbRow, $objectStorage->get('foo', 42));
        $objectStorage->remove('foo', 42);
        $this->assertNull($objectStorage->get('foo', 42));
    }
}
