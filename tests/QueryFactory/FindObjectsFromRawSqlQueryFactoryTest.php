<?php

namespace TheCodingMachine\TDBM\QueryFactory;

use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\TDBMAbstractServiceTest;
use TheCodingMachine\TDBM\TDBMException;

class FindObjectsFromRawSqlQueryFactoryTest extends TDBMAbstractServiceTest
{

    public function testGetSubQueryColumnDescriptors()
    {
        $queryFactory = new FindObjectsFromRawSqlQueryFactory($this->tdbmService, $this->createMock(Schema::class), 'foo', 'bar');
        $this->expectException(TDBMException::class);
        $queryFactory->getSubQueryColumnDescriptors();
    }

    public function testGetMagicSqlSubQuery()
    {
        $queryFactory = new FindObjectsFromRawSqlQueryFactory($this->tdbmService, $this->createMock(Schema::class), 'foo', 'bar');
        $this->expectException(TDBMException::class);
        $queryFactory->getMagicSqlSubQuery();

    }
}
