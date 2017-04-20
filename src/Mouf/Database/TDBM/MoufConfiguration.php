<?php


namespace Mouf\Database\TDBM;


use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\DBAL\Connection;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use Mouf\Database\TDBM\Utils\GeneratorEventDispatcher;
use Mouf\Database\TDBM\Utils\GeneratorListenerInterface;
use Mouf\Database\TDBM\Utils\NamingStrategyInterface;
use Psr\Log\LoggerInterface;

/**
 * Class containing configuration used only for Mouf specific tasks.
 */
class MoufConfiguration extends Configuration
{
    private $daoFactoryInstanceName;

    /**
     * @return mixed
     */
    public function getDaoFactoryInstanceName()
    {
        return $this->daoFactoryInstanceName;
    }

    /**
     * @param mixed $daoFactoryInstanceName
     */
    public function setDaoFactoryInstanceName($daoFactoryInstanceName)
    {
        $this->daoFactoryInstanceName = $daoFactoryInstanceName;
    }


}
