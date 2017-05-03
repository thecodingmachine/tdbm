<?php

namespace Mouf\Database\TDBM\Utils;

class DefaultNamingStrategyTest extends \PHPUnit_Framework_TestCase
{
    public function testGetBeanName()
    {
        $strategy = new DefaultNamingStrategy();
        $strategy->setBeanPrefix('');
        $strategy->setBeanSuffix('Bean');

        $this->assertSame('UserBean', $strategy->getBeanClassName("users"));
        $this->assertSame('UserBean', $strategy->getBeanClassName("user"));
        $this->assertSame('UserCountryBean', $strategy->getBeanClassName("users_countries"));
        $this->assertSame('UserCountryBean', $strategy->getBeanClassName("users countries"));
    }

    public function testGetBaseBeanName()
    {
        $strategy = new DefaultNamingStrategy();
        $strategy->setBaseBeanPrefix('');
        $strategy->setBaseBeanSuffix('BaseBean');
        $this->assertSame('UserBaseBean', $strategy->getBaseBeanClassName("users"));
    }

    public function testGetDaoName()
    {
        $strategy = new DefaultNamingStrategy();
        $strategy->setDaoPrefix('');
        $strategy->setDaoSuffix('Dao');
        $this->assertSame('UserDao', $strategy->getDaoClassName("users"));
    }

    public function testGetBaseDaoName()
    {
        $strategy = new DefaultNamingStrategy();
        $strategy->setBaseDaoPrefix('');
        $strategy->setBaseDaoSuffix('BaseDao');
        $this->assertSame('UserBaseDao', $strategy->getBaseDaoClassName("users"));
    }

    public function testGetBeanNameDefault()
    {
        $strategy = new DefaultNamingStrategy();

        $this->assertSame('User', $strategy->getBeanClassName("users"));
    }

    public function testGetBaseBeanNameDefault()
    {
        $strategy = new DefaultNamingStrategy();
        $this->assertSame('AbstractUser', $strategy->getBaseBeanClassName("users"));
    }

    public function testGetDaoNameDefault()
    {
        $strategy = new DefaultNamingStrategy();
        $this->assertSame('UserDao', $strategy->getDaoClassName("users"));
    }

    public function testGetBaseDaoNameDefault()
    {
        $strategy = new DefaultNamingStrategy();
        $this->assertSame('AbstractUserDao', $strategy->getBaseDaoClassName("users"));
    }

    public function testGetDaoFactory()
    {
        $strategy = new DefaultNamingStrategy();
        $this->assertSame('DaoFactory', $strategy->getDaoFactoryClassName());
    }

    public function testExceptions()
    {
        $strategy = new DefaultNamingStrategy();
        $strategy->setExceptions([
            'chevaux' => 'Cheval'
        ]);
        $this->assertSame('ChevalDao', $strategy->getDaoClassName('chevaux'));
    }
}
