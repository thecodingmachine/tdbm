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

namespace TheCodingMachine\TDBM;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use TheCodingMachine\FluidSchema\FluidSchema;
use TheCodingMachine\TDBM\Utils\DefaultNamingStrategy;
use TheCodingMachine\TDBM\Utils\PathFinder\PathFinder;

abstract class TDBMAbstractServiceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Connection
     */
    protected static $dbConnection;

    /**
     * @var TDBMService
     */
    protected $tdbmService;

    /**
     * @var DummyGeneratorListener
     */
    private $dummyGeneratorListener;

    /**
     * @var ConfigurationInterface
     */
    private $configuration;

    public static function setUpBeforeClass()
    {
        self::resetConnection();

        $config = new \Doctrine\DBAL\Configuration();

        $dbDriver = $GLOBALS['db_driver'];

        if ($dbDriver === 'pdo_sqlite') {
            $dbConnection = self::getConnection();
            $dbConnection->exec('PRAGMA foreign_keys = ON;');
        } else {
            $connectionParams = array(
                'user' => $GLOBALS['db_username'],
                'password' => $GLOBALS['db_password'],
                'host' => $GLOBALS['db_host'],
                'port' => $GLOBALS['db_port'],
                'driver' => $dbDriver,
            );

            $adminConn = DriverManager::getConnection($connectionParams, $config);
            $adminConn->getSchemaManager()->dropAndCreateDatabase($GLOBALS['db_name']);

            $connectionParams['dbname'] = $GLOBALS['db_name'];

            $dbConnection = DriverManager::getConnection($connectionParams, $config);
        }


        self::initSchema($dbConnection);
    }

    private static function resetConnection(): void
    {
        self::$dbConnection = null;
    }

    protected static function getConnection(): Connection
    {
        if (self::$dbConnection === null) {
            $config = new \Doctrine\DBAL\Configuration();

            $dbDriver = $GLOBALS['db_driver'];

            if ($dbDriver === 'pdo_sqlite') {
                $connectionParams = array(
                    'memory' => true,
                    'driver' => 'pdo_sqlite',
                );
            } else {
                $connectionParams = array(
                    'user' => $GLOBALS['db_username'],
                    'password' => $GLOBALS['db_password'],
                    'host' => $GLOBALS['db_host'],
                    'port' => $GLOBALS['db_port'],
                    'driver' => $GLOBALS['db_driver'],
                    'dbname' => $GLOBALS['db_name'],
                );
            }

            self::$dbConnection = DriverManager::getConnection($connectionParams, $config);
        }
        return self::$dbConnection;
    }

    protected function onlyMySql()
    {
        if (!self::getConnection()->getDatabasePlatform() instanceof MySqlPlatform) {
            $this->markTestSkipped('MySQL specific test');
        }
    }

    protected function setUp()
    {
        $this->tdbmService = new TDBMService($this->getConfiguration());
    }

    protected function getDummyGeneratorListener() : DummyGeneratorListener
    {
        if ($this->dummyGeneratorListener === null) {
            $this->dummyGeneratorListener = new DummyGeneratorListener();
        }
        return $this->dummyGeneratorListener;
    }

    protected function getConfiguration() : ConfigurationInterface
    {
        if ($this->configuration === null) {

            $this->configuration = new Configuration('TheCodingMachine\\TDBM\\Test\\Dao\\Bean', 'TheCodingMachine\\TDBM\\Test\\Dao', self::getConnection(), $this->getNamingStrategy(), new ArrayCache(), null, null, [$this->getDummyGeneratorListener()]);
            $this->configuration->setPathFinder(new PathFinder(null, dirname(__DIR__, 4)));
        }
        return $this->configuration;
    }

    protected function getNamingStrategy()
    {
        $strategy = new DefaultNamingStrategy();
        $strategy->setBeanPrefix('');
        $strategy->setBeanSuffix('Bean');
        $strategy->setBaseBeanPrefix('');
        $strategy->setBaseBeanSuffix('BaseBean');
        $strategy->setDaoPrefix('');
        $strategy->setDaoSuffix('Dao');
        $strategy->setBaseDaoPrefix('');
        $strategy->setBaseDaoSuffix('BaseDao');

        return $strategy;
    }

    private static function initSchema(Connection $connection): void
    {
        $fromSchema = $connection->getSchemaManager()->createSchema();
        $toSchema = clone $fromSchema;

        $db = new FluidSchema($toSchema);

        $db->table('country')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('label')->string(255);

        $db->table('person')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('name')->string(255);

        $toSchema->getTable('person')
            ->addColumn(
                'created_at',
                'datetime',
                ['columnDefinition' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP']
            );

        $db->table('person')
            ->column('modified_at')->datetime()->null()
            ->column('order')->integer()->null();


        $db->table('contact')
            ->extends('person')
            ->column('email')->string(255)
            ->column('manager_id')->references('contact')->null();

        $db->table('users')
            ->extends('contact')
            ->column('login')->string(255)
            ->column('password')->string(255)->null()
            ->column('status')->string(10)->null()->default(null)
            ->column('country_id')->references('country');

        $db->table('rights')
            ->column('label')->string(255)->primaryKey()->comment('Non autoincrementable primary key');

        $db->table('roles')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('name')->string(255)
            ->column('created_at')->date()->null()
            ->column('status')->boolean()->null()->default(1);

        $db->table('roles_rights')
            ->column('role_id')->references('roles')
            ->column('right_label')->references('rights')->then()
            ->primaryKey(['role_id', 'right_label']);

        $db->table('users_roles')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('user_id')->references('users')
            ->column('role_id')->references('roles');

        $db->table('all_nullable')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('label')->string(255)->null()
            ->column('country_id')->references('country')->null();

        $db->table('animal')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('name')->string(45)->index()
            ->column('order')->integer()->null();

        $db->table('dog')
            ->extends('animal')
            ->column('race')->string(45)->null();

        $db->table('cat')
            ->extends('animal')
            ->column('cuteness_level')->integer()->null();

        $db->table('boats')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('name')->string(255)
            ->column('anchorage_country')->references('country')->notNull();

        $db->table('sailed_countries')
            ->column('boat_id')->references('boats')
            ->column('country_id')->references('country')
            ->then()->primaryKey(['boat_id', 'country_id']);

        $db->table('category')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('label')->string(255)
            ->column('parent_id')->references('category')->null();

        $db->table('article')
            ->column('id')->string(36)->primaryKey()->comment('@UUID')
            ->column('content')->string(255);

        $db->table('article2')
            ->column('id')->string(36)->primaryKey()->comment('@UUID v4')
            ->column('content')->string(255);

        $toSchema->getTable('users')
            ->addUniqueIndex(['login'], 'users_login_idx')
            ->addUniqueIndex(['login'], 'users_login_idx_2') // We create the same index twice
            ->addIndex(['status', 'country_id'], 'users_status_country_idx');

        $sqlStmts = $toSchema->getMigrateFromSql($fromSchema, $connection->getDatabasePlatform());

        foreach ($sqlStmts as $sqlStmt) {
            $connection->exec($sqlStmt);
        }

        $connection->insert('country', [
            'label' => 'France',
        ]);
        $connection->insert('country', [
            'label' => 'UK',
        ]);
        $connection->insert('country', [
            'label' => 'Jamaica',
        ]);

        $connection->insert('person', [
            'name' => 'John Smith',
            'created_at' => '2015-10-24 11:57:13',
        ]);
        $connection->insert('person', [
            'name' => 'Jean Dupont',
            'created_at' => '2015-10-24 11:57:13',
        ]);
        $connection->insert('person', [
            'name' => 'Robert Marley',
            'created_at' => '2015-10-24 11:57:13',
        ]);
        $connection->insert('person', [
            'name' => 'Bill Shakespeare',
            'created_at' => '2015-10-24 11:57:13',
        ]);

        $connection->insert('contact', [
            'id' => 1,
            'email' => 'john@smith.com',
            'manager_id' => null,
        ]);
        $connection->insert('contact', [
            'id' => 2,
            'email' => 'jean@dupont.com',
            'manager_id' => null,
        ]);
        $connection->insert('contact', [
            'id' => 3,
            'email' => 'robert@marley.com',
            'manager_id' => null,
        ]);
        $connection->insert('contact', [
            'id' => 4,
            'email' => 'bill@shakespeare.com',
            'manager_id' => 1,
        ]);

        $connection->insert('rights', [
            'label' => 'CAN_SING',
        ]);
        $connection->insert('rights', [
            'label' => 'CAN_WRITE',
        ]);

        $connection->insert('roles', [
            'name' => 'Admins',
            'created_at' => '2015-10-24'
        ]);
        $connection->insert('roles', [
            'name' => 'Writers',
            'created_at' => '2015-10-24'
        ]);
        $connection->insert('roles', [
            'name' => 'Singers',
            'created_at' => '2015-10-24'
        ]);

        $connection->insert('roles_rights', [
            'role_id' => 1,
            'right_label' => 'CAN_SING'
        ]);
        $connection->insert('roles_rights', [
            'role_id' => 3,
            'right_label' => 'CAN_SING'
        ]);
        $connection->insert('roles_rights', [
            'role_id' => 1,
            'right_label' => 'CAN_WRITE'
        ]);
        $connection->insert('roles_rights', [
            'role_id' => 2,
            'right_label' => 'CAN_WRITE'
        ]);

        $connection->insert('users', [
            'id' => 1,
            'login' => 'john.smith',
            'password' => null,
            'status' => 'on',
            'country_id' => 2
        ]);
        $connection->insert('users', [
            'id' => 2,
            'login' => 'jean.dupont',
            'password' => null,
            'status' => 'on',
            'country_id' => 1
        ]);
        $connection->insert('users', [
            'id' => 3,
            'login' => 'robert.marley',
            'password' => null,
            'status' => 'off',
            'country_id' => 3
        ]);
        $connection->insert('users', [
            'id' => 4,
            'login' => 'bill.shakespeare',
            'password' => null,
            'status' => 'off',
            'country_id' => 2
        ]);

        $connection->insert('users_roles', [
            'user_id' => 1,
            'role_id' => 1,
        ]);
        $connection->insert('users_roles', [
            'user_id' => 2,
            'role_id' => 1,
        ]);
        $connection->insert('users_roles', [
            'user_id' => 3,
            'role_id' => 3,
        ]);
        $connection->insert('users_roles', [
            'user_id' => 4,
            'role_id' => 2,
        ]);
        $connection->insert('users_roles', [
            'user_id' => 3,
            'role_id' => 2,
        ]);
    }
}
