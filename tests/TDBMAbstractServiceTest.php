<?php
declare(strict_types=1);

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
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Event\Listeners\OracleSessionInit;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use TheCodingMachine\FluidSchema\FluidSchema;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;
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
        } elseif ($dbDriver === 'oci8') {
            $connectionParams = array(
                'servicename' => 'XE',
                'user' => $GLOBALS['db_admin_username'],
                // Because of issues in DBAL, admin and normal user password have to be the same.
                'password' => $GLOBALS['db_password'],
                'host' => $GLOBALS['db_host'],
                'port' => $GLOBALS['db_port'],
                'driver' => $GLOBALS['db_driver'],
                'dbname' => $GLOBALS['db_admin_username'],
                'charset' => 'AL32UTF8',
            );

            $adminConn = DriverManager::getConnection($connectionParams, $config);

            // When dropAndCreateDatabase is run several times, Oracle can have some issues releasing the TDBM user.
            // Let's forcefully delete the connection!
            $adminConn->exec("select 'alter system kill session ''' || sid || ',' || serial# || ''';' from v\$session where username = '".strtoupper($GLOBALS['db_name'])."'");

            $adminConn->getSchemaManager()->dropAndCreateDatabase($GLOBALS['db_name']);

            $dbConnection = self::getConnection();
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
        if (self::$dbConnection !== null) {
            self::$dbConnection->close();
        }
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
                self::$dbConnection = DriverManager::getConnection($connectionParams, $config);
            } elseif ($dbDriver === 'oci8') {
                $evm = new EventManager();
                $evm->addEventSubscriber(new OracleSessionInit(array(
                    'NLS_TIME_FORMAT' => 'HH24:MI:SS',
                    'NLS_DATE_FORMAT' => 'YYYY-MM-DD HH24:MI:SS',
                    'NLS_TIMESTAMP_FORMAT' => 'YYYY-MM-DD HH24:MI:SS',
                )));

                $connectionParams = array(
                    'servicename' => 'XE',
                    'user' => $GLOBALS['db_username'],
                    'password' => $GLOBALS['db_password'],
                    'host' => $GLOBALS['db_host'],
                    'port' => $GLOBALS['db_port'],
                    'driver' => $GLOBALS['db_driver'],
                    'dbname' => $GLOBALS['db_name'],
                    'charset' => 'AL32UTF8',
                );
                self::$dbConnection = DriverManager::getConnection($connectionParams, $config, $evm);
                self::$dbConnection->setAutoCommit(true);
            } else {
                $connectionParams = array(
                    'user' => $GLOBALS['db_username'],
                    'password' => $GLOBALS['db_password'],
                    'host' => $GLOBALS['db_host'],
                    'port' => $GLOBALS['db_port'],
                    'driver' => $GLOBALS['db_driver'],
                    'dbname' => $GLOBALS['db_name'],
                );
                self::$dbConnection = DriverManager::getConnection($connectionParams, $config);
            }
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
        $strategy = new DefaultNamingStrategy(AnnotationParser::buildWithDefaultAnnotations([]), self::getConnection()->getSchemaManager());
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

        $db = new FluidSchema($toSchema, new \TheCodingMachine\FluidSchema\DefaultNamingStrategy($connection->getDatabasePlatform()));

        $db->table('country')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('label')->string(255)->unique();

        $db->table('person')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('name')->string(255);

        if ($connection->getDatabasePlatform() instanceof OraclePlatform) {
            $toSchema->getTable($connection->quoteIdentifier('person'))
                ->addColumn(
                    $connection->quoteIdentifier('created_at'),
                    'datetime',
                    ['columnDefinition' => 'TIMESTAMP(0) DEFAULT SYSDATE NOT NULL']
                );
        } else {
            $toSchema->getTable('person')
                ->addColumn(
                    $connection->quoteIdentifier('created_at'),
                    'datetime',
                    ['columnDefinition' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP']
                );
        }

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
            ->column('UPPERCASE_COLUMN')->string(45)->null()
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
            ->column('anchorage_country')->references('country')->notNull()->then()
            ->column('current_country')->references('country')->null()->then()
            ->column('length')->decimal(10, 2)->null()->then()
            ->unique(['anchorage_country', 'name']);

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
            ->column('content')->string(255)
            ->column('author_id')->references('users')->null();

        $db->table('article2')
            ->column('id')->string(36)->primaryKey()->comment('@UUID v4')
            ->column('content')->string(255);

        $db->table('files')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('file')->blob();

        $toSchema->getTable('users')
            ->addUniqueIndex([$connection->quoteIdentifier('login')], 'users_login_idx')
            ->addIndex([$connection->quoteIdentifier('status'), $connection->quoteIdentifier('country_id')], 'users_status_country_idx');

        // We create the same index twice
        // except for Oracle that won't let us create twice the same index.
        if (!$connection->getDatabasePlatform() instanceof OraclePlatform) {
            $toSchema->getTable('users')
                ->addUniqueIndex([$connection->quoteIdentifier('login')], 'users_login_idx_2');
        }

        // A table with a foreign key that references a non primary key.
        $db->table('ref_no_prim_key')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('from')->string(50)
            ->column('to')->string(50)->unique();

        $toSchema->getTable($connection->quoteIdentifier('ref_no_prim_key'))->addForeignKeyConstraint($connection->quoteIdentifier('ref_no_prim_key'), [$connection->quoteIdentifier('from')], [$connection->quoteIdentifier('to')]);

        // A table with multiple primary keys.
        $db->table('states')
            ->column('country_id')->references('country')
            ->column('code')->string(3)
            ->column('name')->string(50)->then()
            ->primaryKey(['country_id', 'code']);

        $db->table('contracts')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('signature_date')->datetimeImmutable()
            ->column('contact_id')->references('contact')->notNull()
            ->column('country_id')->references('country')->null();
        $toSchema->getTable('contracts')->addUniqueIndex(['contact_id', 'country_id']);


        $sqlStmts = $toSchema->getMigrateFromSql($fromSchema, $connection->getDatabasePlatform());

        foreach ($sqlStmts as $sqlStmt) {
            $connection->exec($sqlStmt);
        }

        self::insert($connection, 'country', [
            'label' => 'France',
        ]);
        self::insert($connection, 'country', [
            'label' => 'UK',
        ]);
        self::insert($connection, 'country', [
            'label' => 'Jamaica',
        ]);

        self::insert($connection, 'person', [
            'name' => 'John Smith',
            'created_at' => '2015-10-24 11:57:13',
        ]);
        self::insert($connection, 'person', [
            'name' => 'Jean Dupont',
            'created_at' => '2015-10-24 11:57:13',
        ]);
        self::insert($connection, 'person', [
            'name' => 'Robert Marley',
            'created_at' => '2015-10-24 11:57:13',
        ]);
        self::insert($connection, 'person', [
            'name' => 'Bill Shakespeare',
            'created_at' => '2015-10-24 11:57:13',
        ]);

        self::insert($connection, 'contact', [
            'id' => 1,
            'email' => 'john@smith.com',
            'manager_id' => null,
        ]);
        self::insert($connection, 'contact', [
            'id' => 2,
            'email' => 'jean@dupont.com',
            'manager_id' => null,
        ]);
        self::insert($connection, 'contact', [
            'id' => 3,
            'email' => 'robert@marley.com',
            'manager_id' => null,
        ]);
        self::insert($connection, 'contact', [
            'id' => 4,
            'email' => 'bill@shakespeare.com',
            'manager_id' => 1,
        ]);

        self::insert($connection, 'rights', [
            'label' => 'CAN_SING',
        ]);
        self::insert($connection, 'rights', [
            'label' => 'CAN_WRITE',
        ]);

        self::insert($connection, 'roles', [
            'name' => 'Admins',
            'created_at' => '2015-10-24'
        ]);
        self::insert($connection, 'roles', [
            'name' => 'Writers',
            'created_at' => '2015-10-24'
        ]);
        self::insert($connection, 'roles', [
            'name' => 'Singers',
            'created_at' => '2015-10-24'
        ]);

        self::insert($connection, 'roles_rights', [
            'role_id' => 1,
            'right_label' => 'CAN_SING'
        ]);
        self::insert($connection, 'roles_rights', [
            'role_id' => 3,
            'right_label' => 'CAN_SING'
        ]);
        self::insert($connection, 'roles_rights', [
            'role_id' => 1,
            'right_label' => 'CAN_WRITE'
        ]);
        self::insert($connection, 'roles_rights', [
            'role_id' => 2,
            'right_label' => 'CAN_WRITE'
        ]);

        self::insert($connection, 'users', [
            'id' => 1,
            'login' => 'john.smith',
            'password' => null,
            'status' => 'on',
            'country_id' => 2
        ]);
        self::insert($connection, 'users', [
            'id' => 2,
            'login' => 'jean.dupont',
            'password' => null,
            'status' => 'on',
            'country_id' => 1
        ]);
        self::insert($connection, 'users', [
            'id' => 3,
            'login' => 'robert.marley',
            'password' => null,
            'status' => 'off',
            'country_id' => 3
        ]);
        self::insert($connection, 'users', [
            'id' => 4,
            'login' => 'bill.shakespeare',
            'password' => null,
            'status' => 'off',
            'country_id' => 2
        ]);

        self::insert($connection, 'users_roles', [
            'user_id' => 1,
            'role_id' => 1,
        ]);
        self::insert($connection, 'users_roles', [
            'user_id' => 2,
            'role_id' => 1,
        ]);
        self::insert($connection, 'users_roles', [
            'user_id' => 3,
            'role_id' => 3,
        ]);
        self::insert($connection, 'users_roles', [
            'user_id' => 4,
            'role_id' => 2,
        ]);
        self::insert($connection, 'users_roles', [
            'user_id' => 3,
            'role_id' => 2,
        ]);

        self::insert($connection, 'ref_no_prim_key', [
            'from' => 'foo',
            'to' => 'foo',
        ]);

        self::insert($connection, 'contracts', [
            'contact_id' => 1,
            'country_id' => null,
            'signature_date' => '2018-03-12 11:57:13'
        ]);
        self::insert($connection, 'contracts', [
            'contact_id' => 1,
            'country_id' => 1,
            'signature_date' => '2018-03-12 11:57:13'
        ]);
    }

    protected static function insert(Connection $connection, string $tableName, array $data): void
    {
        $quotedData = [];
        foreach ($data as $id => $value) {
            $quotedData[$connection->quoteIdentifier($id)] = $value;
        }
        $connection->insert($connection->quoteIdentifier($tableName), $quotedData);
    }

    protected static function delete(Connection $connection, string $tableName, array $data): void
    {
        $quotedData = [];
        foreach ($data as $id => $value) {
            $quotedData[$connection->quoteIdentifier($id)] = $value;
        }
        $connection->delete($connection->quoteIdentifier($tableName), $quotedData);
    }
}
