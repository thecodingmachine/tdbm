<?php

namespace TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\TDBMException;

class ManyToOneDataLoaderTest extends TestCase
{

    public function testGet()
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAll')->willReturn([]);
        $dataLoader = new ManyToOneDataLoader($connection, 'SELECT * FROM users', 'id');

        $this->expectException(TDBMException::class);
        $dataLoader->get('42');
    }
}
