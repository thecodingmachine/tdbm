<?php

namespace TheCodingMachine\TDBM;

use PHPUnit\Framework\TestCase;

class NativeWeakrefObjectStorageTest extends TestCase
{
    public function testObjectStorage(): void
    {
        if (!\class_exists(\WeakReference::class)) {
            $this->markTestSkipped('PHP 7.4+ only');
            return;
        }
        $objectStorage = new NativeWeakrefObjectStorage();
        $this->assertNull($objectStorage->get('foo', 42));
        $dbRow = $this->createMock(DbRow::class);
        $objectStorage->set('foo', 42, $dbRow);
        $this->assertSame($dbRow, $objectStorage->get('foo', 42));
        $objectStorage->remove('foo', 42);
        $this->assertNull($objectStorage->get('foo', 42));
    }

    public function testDanglingPointers(): void
    {
        if (!\class_exists(\WeakReference::class)) {
            $this->markTestSkipped('PHP 7.4+ only');
            return;
        }
        $objectStorage = new NativeWeakrefObjectStorage();
        $dbRow = $this->createMock(DbRow::class);

        for ($i=0; $i<10001; $i++) {
            $objectStorage->set('foo', $i, clone $dbRow);
        }
        $this->assertNull($objectStorage->get('foo', 42));
    }
}
