<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class DbalUtilsTest extends TestCase
{

    public function testGenerateArrayTypes(): void
    {
        $params = [
            'key1' => 'foo',
            'key2' => [1,2,3],
            'key3' => [1,2,'baz'],
        ];

        $this->assertSame(['key2'=>Connection::PARAM_INT_ARRAY, 'key3'=>Connection::PARAM_STR_ARRAY], DbalUtils::generateArrayTypes($params));
    }
}
