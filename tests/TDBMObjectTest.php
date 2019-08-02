<?php

namespace TheCodingMachine\TDBM;

use PHPUnit\Framework\TestCase;

class TDBMObjectTest extends TestCase
{

    public function testJsonSerialize()
    {
        $object = new TDBMObject();
        $this->expectException(TDBMException::class);
        $this->expectExceptionMessage('Json serialization is only implemented for generated beans.');
        $object->jsonSerialize();
    }
}
