<?php

namespace TheCodingMachine\TDBM\QueryFactory;

use Doctrine\Common\Cache\VoidCache;
use TheCodingMachine\TDBM\TDBMAbstractServiceTest;
use TheCodingMachine\TDBM\TDBMException;

class FindObjectsFromRawSqlQueryFactoryTest extends TDBMAbstractServiceTest
{
    public function testGetSubQueryColumnDescriptors(): void
    {
        $queryFactory = new FindObjectsFromRawSqlQueryFactory($this->tdbmService, $this->tdbmService->getConnection()->getSchemaManager()->createSchema(), 'country', 'SELECT country.* FROM country', null, new VoidCache());
        $this->expectException(TDBMException::class);
        $queryFactory->getSubQueryColumnDescriptors();
    }

    public function testGetMagicSqlSubQuery(): void
    {
        $queryFactory = new FindObjectsFromRawSqlQueryFactory($this->tdbmService, $this->tdbmService->getConnection()->getSchemaManager()->createSchema(), 'country', 'SELECT country.* FROM country', null, new VoidCache());
        $this->expectException(TDBMException::class);
        $queryFactory->getMagicSqlSubQuery();
    }
}
