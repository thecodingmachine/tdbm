<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;

class DbalUtilsTest extends TestCase
{
    public function testGenerateTypes(): void
    {
        $params = [
            'key1' => 'foo',
            'key2' => [1,2,3],
            'key3' => [1,2,'baz'],
            'key4' => 1,
        ];

        $this->assertSame(['key2' => ArrayParameterType::INTEGER, 'key3' => ArrayParameterType::STRING, 'key4' => ParameterType::INTEGER], DbalUtils::generateTypes($params));
    }
}
