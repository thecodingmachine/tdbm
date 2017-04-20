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
    private $daoFactoryInstanceName = 'daoFactory';

    /**
     * @return string
     */
    public function getDaoFactoryInstanceName() : string
    {
        return $this->daoFactoryInstanceName;
    }

    /**
     * @param string $daoFactoryInstanceName
     */
    public function setDaoFactoryInstanceName(string $daoFactoryInstanceName)
    {
        $this->daoFactoryInstanceName = $daoFactoryInstanceName;
    }


}
