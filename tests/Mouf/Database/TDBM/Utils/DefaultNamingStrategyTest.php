<?php

namespace Mouf\Database\TDBM\Utils;


class DefaultNamingStrategyTest extends \PHPUnit_Framework_TestCase
{
    public function testGetBeanName()
    {
        $strategy = new DefaultNamingStrategy();
        $this->assertSame('UserBean', $strategy->getBeanClassName("users"));
        $this->assertSame('UserBean', $strategy->getBeanClassName("user"));
        $this->assertSame('UserCountryBean', $strategy->getBeanClassName("users_countries"));
        $this->assertSame('UserCountryBean', $strategy->getBeanClassName("users countries"));
    }

    public function testGetBaseBeanName()
    {
        $strategy = new DefaultNamingStrategy();
        $this->assertSame('UserBaseBean', $strategy->getBaseBeanClassName("users"));
    }

    public function testGetDaoName()
    {
        $strategy = new DefaultNamingStrategy();
        $this->assertSame('UserDao', $strategy->getDaoClassName("users"));
    }

    public function testGetBaseDaoName()
    {
        $strategy = new DefaultNamingStrategy();
        $this->assertSame('UserBaseDao', $strategy->getBaseDaoClassName("users"));
    }
}
