<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\MySqlSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use TheCodingMachine\TDBM\TDBMAbstractServiceTest;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;

class DefaultNamingStrategyTest extends TDBMAbstractServiceTest
{
    private function getDefaultNamingStrategy()
    {
        return new DefaultNamingStrategy(AnnotationParser::buildWithDefaultAnnotations([]), $this->getConnection()->getSchemaManager());
    }

    /**
     * @param Table[] $tables
     * @return DefaultNamingStrategy
     */
    private function getDefaultNamingStrategyWithStubTables(array $tables)
    {
        $stubSchemaManager = new class ($tables, self::getConnection(), new MySqlPlatform()) extends MySqlSchemaManager {
            private $tables;

            /**
             * @param Table[] $tables
             */
            public function __construct(array $tables, \Doctrine\DBAL\Connection $conn, AbstractPlatform $platform = null)
            {
                parent::__construct($conn, $platform);
                $this->tables = $tables;
            }

            public function createSchema()
            {
                return new Schema($this->tables, [], $this->createSchemaConfig(), []);
            }
        };

        return new DefaultNamingStrategy(AnnotationParser::buildWithDefaultAnnotations([]), $stubSchemaManager);
    }

    public function testGetBeanName(): void
    {
        $strategy = $this->getDefaultNamingStrategy();
        $strategy->setBeanPrefix('');
        $strategy->setBeanSuffix('Bean');

        $this->assertSame('UserBean', $strategy->getBeanClassName("users"));


        $strategy2 = $this->getDefaultNamingStrategyWithStubTables([new Table('user'), new Table('users_countries'), new Table('users countries')]);
        $strategy2->setBeanPrefix('');
        $strategy2->setBeanSuffix('Bean');
        $this->assertSame('UserBean', $strategy2->getBeanClassName("user"));
        $this->assertSame('UserCountryBean', $strategy2->getBeanClassName("users_countries"));
        $this->assertSame('UserCountryBean', $strategy2->getBeanClassName("users countries"));
    }

    public function testGetBaseBeanName(): void
    {
        $strategy = $this->getDefaultNamingStrategy();
        $strategy->setBaseBeanPrefix('');
        $strategy->setBaseBeanSuffix('BaseBean');
        $this->assertSame('UserBaseBean', $strategy->getBaseBeanClassName("users"));
    }

    public function testGetDaoName(): void
    {
        $strategy = $this->getDefaultNamingStrategy();
        $strategy->setDaoPrefix('');
        $strategy->setDaoSuffix('Dao');
        $this->assertSame('UserDao', $strategy->getDaoClassName("users"));
    }

    public function testGetBaseDaoName(): void
    {
        $strategy = $this->getDefaultNamingStrategy();
        $strategy->setBaseDaoPrefix('');
        $strategy->setBaseDaoSuffix('BaseDao');
        $this->assertSame('UserBaseDao', $strategy->getBaseDaoClassName("users"));
    }

    public function testGetBeanNameDefault(): void
    {
        $strategy = $this->getDefaultNamingStrategy();

        $this->assertSame('User', $strategy->getBeanClassName("users"));
    }

    public function testGetBaseBeanNameDefault(): void
    {
        $strategy = $this->getDefaultNamingStrategy();
        $this->assertSame('AbstractUser', $strategy->getBaseBeanClassName("users"));
    }

    public function testGetDaoNameDefault(): void
    {
        $strategy = $this->getDefaultNamingStrategy();
        $this->assertSame('UserDao', $strategy->getDaoClassName("users"));
    }

    public function testGetBaseDaoNameDefault(): void
    {
        $strategy = $this->getDefaultNamingStrategy();
        $this->assertSame('AbstractUserDao', $strategy->getBaseDaoClassName("users"));
    }

    public function testGetDaoFactory(): void
    {
        $strategy = $this->getDefaultNamingStrategy();
        $this->assertSame('DaoFactory', $strategy->getDaoFactoryClassName());
    }

    public function testExceptions(): void
    {
        $table = new Table('chevaux');
        $strategy = $this->getDefaultNamingStrategyWithStubTables([$table]);
        $strategy->setExceptions([
            'chevaux' => 'Cheval'
        ]);
        $this->assertSame('ChevalDao', $strategy->getDaoClassName('chevaux'));
    }

    public function testBeanAnnotation(): void
    {
        $table = new Table('chevaux', [], [], [], 0, ['comment' => '@Bean(name="Cheval")']);
        $strategy = $this->getDefaultNamingStrategyWithStubTables([$table]);
        $this->assertSame('ChevalDao', $strategy->getDaoClassName('chevaux'));
    }

    public function testUppercaseNames(): void
    {
        $strategy = $this->getDefaultNamingStrategy();
        $strategy->setDaoPrefix('');
        $strategy->setDaoSuffix('Dao');
        $this->assertSame('UserDao', $strategy->getDaoClassName("USERS"));
    }
}
