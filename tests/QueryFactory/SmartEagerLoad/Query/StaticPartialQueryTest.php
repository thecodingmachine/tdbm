<?php

namespace TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\Query;

use Doctrine\DBAL\Connection;
use Mouf\Database\MagicQuery;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\StorageNode;
use TheCodingMachine\TDBM\TDBMException;

class StaticPartialQueryTest extends TestCase
{
    public function testRegisterDataLoader()
    {
        $query = new StaticPartialQuery('FROM users', ['foo'=>42], ['users'], $this->createMock(StorageNode::class), new MagicQuery());
        $this->expectException(TDBMException::class);
        $query->registerDataLoader($this->createMock(Connection::class));
    }

    public function testGetMagicQuery()
    {
        $magicQuery = new MagicQuery();
        $query = new StaticPartialQuery('FROM users', ['foo'=>42], ['users'], $this->createMock(StorageNode::class), $magicQuery);
        $this->assertSame($magicQuery, $query->getMagicQuery());
    }

    public function testGetParameters()
    {
        $magicQuery = new MagicQuery();
        $query = new StaticPartialQuery('FROM users', ['foo'=>42], ['users'], $this->createMock(StorageNode::class), $magicQuery);
        $this->assertSame(['foo'=>42], $query->getParameters());
    }
}
