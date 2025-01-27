<?php

namespace TheCodingMachine\TDBM\QueryFactory;

use Doctrine\DBAL\Schema\Schema;
use TheCodingMachine\TDBM\TDBMAbstractServiceTest;
use TheCodingMachine\TDBM\TDBMException;

class FindObjectsFromRawSqlQueryFactoryTest extends TDBMAbstractServiceTest
{
    public function testGetSubQueryColumnDescriptors(): void
    {
        $queryFactory = new FindObjectsFromRawSqlQueryFactory($this->tdbmService, $this->tdbmService->getConnection()->createSchemaManager()->createSchema(), 'country', 'SELECT country.* FROM country');
        $this->expectException(TDBMException::class);
        $queryFactory->getSubQueryColumnDescriptors();
    }

    public function testGetMagicSqlSubQuery(): void
    {
        $queryFactory = new FindObjectsFromRawSqlQueryFactory($this->tdbmService, $this->tdbmService->getConnection()->createSchemaManager()->createSchema(), 'country', 'SELECT country.* FROM country');
        $this->expectException(TDBMException::class);
        $queryFactory->getMagicSqlSubQuery();
    }
}
