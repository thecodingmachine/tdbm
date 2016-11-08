<?php

/*
 Copyright (C) 2006-2014 David Négrier - THE CODING MACHINE

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace Mouf\Database\TDBM;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 */
abstract class TDBMAbstractServiceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Connection
     */
    protected $dbConnection;

    /**
     * @var TDBMService
     */
    protected $tdbmService;

    public static function setUpBeforeClass()
    {
        $config = new \Doctrine\DBAL\Configuration();

        $connectionParams = array(
            'user' => $GLOBALS['db_username'],
            'password' => $GLOBALS['db_password'],
            'host' => $GLOBALS['db_host'],
            'port' => $GLOBALS['db_port'],
            'driver' => $GLOBALS['db_driver'],
        );

        $adminConn = DriverManager::getConnection($connectionParams, $config);
        $adminConn->getSchemaManager()->dropAndCreateDatabase($GLOBALS['db_name']);

        $connectionParams['dbname'] = $GLOBALS['db_name'];

        $dbConnection = DriverManager::getConnection($connectionParams, $config);

        self::loadSqlFile($dbConnection, __DIR__.'/../../../sql/tdbmunittest.sql');
    }

    protected function setUp()
    {
        $config = new \Doctrine\DBAL\Configuration();

        $connectionParams = array(
            'user' => $GLOBALS['db_username'],
            'password' => $GLOBALS['db_password'],
            'host' => $GLOBALS['db_host'],
            'port' => $GLOBALS['db_port'],
            'driver' => $GLOBALS['db_driver'],
            'dbname' => $GLOBALS['db_name'],
        );

        $this->dbConnection = DriverManager::getConnection($connectionParams, $config);
        $this->tdbmService = new TDBMService($this->dbConnection);
    }

    protected static function loadSqlFile(Connection $connection, $sqlFile)
    {
        $sql = file_get_contents($sqlFile);

        $stmt = $connection->prepare($sql);
        $stmt->execute();
        /*do {
            // Required due to "MySQL has gone away!" issue
            $stmt->fetch();
            $stmt->closeCursor();
        } while ($stmt->nextRowset());*/
    }
}
