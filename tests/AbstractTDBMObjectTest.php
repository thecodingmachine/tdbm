<?php

namespace TheCodingMachine\TDBM;

use PHPUnit\Framework\TestCase;

class AbstractTDBMObjectTest extends TestCase
{
    public function testGetManyToManyRelationshipDescriptor()
    {
        $object = new TDBMObject();
        $this->expectException(TDBMException::class);
        $this->expectExceptionMessage('Could not find many to many relationship descriptor key for "foo"');
        $object->_getManyToManyRelationshipDescriptor('foo');
    }
}
