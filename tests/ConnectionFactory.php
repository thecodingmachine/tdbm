<?php

namespace TheCodingMachine\TDBM;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Event\Listeners\OracleSessionInit;

class ConnectionFactory
{
    /**
     * Resets the database and returns a (root) connection
     */
    public static function resetDatabase(
        string $dbDriver,
        ?string $dbHost = null,
        ?string $dbPort = null,
        ?string $dbUserName = null,
        ?string $dbAdminUserName = null,
        ?string $dbPassword = null,
        ?string $dbName = null
    ): Connection {
        $config = new \Doctrine\DBAL\Configuration();

        if ($dbDriver === 'pdo_sqlite') {
            $dbConnection = self::getConnection();
            $dbConnection->exec('PRAGMA foreign_keys = ON;');
        } elseif ($dbDriver === 'oci8') {
            $adminConn = self::createConnection($dbDriver, $dbHost, $dbPort, $dbAdminUserName, $dbPassword, $dbAdminUserName);

            // When dropAndCreateDatabase is run several times, Oracle can have some issues releasing the TDBM user.
            // Let's forcefully delete the connection!
            //$adminConn->exec("select 'alter system kill session ''' || sid || ',' || serial# || ''';' from v\$session where username = '".strtoupper($dbName)."'");

            $adminConn->createSchemaManager()->dropAndCreateDatabase($dbName);

            $dbConnection = self::createConnection($dbDriver, $dbHost, $dbPort, $dbName, $dbPassword, $dbName);
        } else {
            $connectionParams = array(
                'user' => $dbUserName,
                'password' => $dbPassword,
                'host' => $dbHost,
                'port' => $dbPort,
                'driver' => $dbDriver,
            );

            $adminConn = DriverManager::getConnection($connectionParams, $config);

            $adminConn->createSchemaManager()->dropAndCreateDatabase($dbName);

            $connectionParams['dbname'] = $dbName;

            $dbConnection = DriverManager::getConnection($connectionParams, $config);
        }

        return $dbConnection;
    }

    public static function createConnection(
        string $dbDriver,
        ?string $dbHost = null,
        ?string $dbPort = null,
        ?string $dbUserName = null,
        ?string $dbPassword = null,
        ?string $dbName = null
    ): Connection {
        $config = new \Doctrine\DBAL\Configuration();

        $dbDriver = $dbDriver;

        if ($dbDriver === 'pdo_sqlite') {
            $connectionParams = array(
                'memory' => true,
                'driver' => 'pdo_sqlite',
            );
            $dbConnection = DriverManager::getConnection($connectionParams, $config);
        } elseif ($dbDriver === 'oci8') {
            $evm = new EventManager();
            $evm->addEventSubscriber(new OracleSessionInit(array(
                'NLS_TIME_FORMAT' => 'HH24:MI:SS',
                'NLS_DATE_FORMAT' => 'YYYY-MM-DD HH24:MI:SS',
                'NLS_TIMESTAMP_FORMAT' => 'YYYY-MM-DD HH24:MI:SS',
            )));

            $connectionParams = array(
                'servicename' => 'XE',
                'user' => $dbUserName,
                'password' => $dbPassword,
                'host' => $dbHost,
                'port' => $dbPort,
                'driver' => $dbDriver,
                'dbname' => $dbName,
                'charset' => 'AL32UTF8',
            );
            $dbConnection = DriverManager::getConnection($connectionParams, $config, $evm);
            $dbConnection->setAutoCommit(true);
        } else {
            $connectionParams = array(
                'user' => $dbUserName,
                'password' => $dbPassword,
                'host' => $dbHost,
                'port' => $dbPort,
                'driver' => $dbDriver,
                'dbname' => $dbName,
            );
            $dbConnection = DriverManager::getConnection($connectionParams, $config);
        }

        return $dbConnection;
    }
}
