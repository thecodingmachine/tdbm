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

use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Event\Listeners\OracleSessionInit;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\FluidSchema\FluidSchema;
use TheCodingMachine\FluidSchema\TdbmFluidSchema;
use TheCodingMachine\TDBM\Fixtures\Interfaces\TestUserDaoInterface;
use TheCodingMachine\TDBM\Fixtures\Interfaces\TestUserInterface;
use TheCodingMachine\TDBM\Fixtures\Traits\TestOtherUserTrait;
use TheCodingMachine\TDBM\Fixtures\Traits\TestUserDaoTrait;
use TheCodingMachine\TDBM\Fixtures\Traits\TestUserTrait;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;
use TheCodingMachine\TDBM\Utils\Annotation\AddInterface;
use TheCodingMachine\TDBM\Utils\DefaultNamingStrategy;
use TheCodingMachine\TDBM\Utils\PathFinder\PathFinder;
use function stripos;
use const PHP_EOL;

abstract class TDBMAbstractServiceTest extends TestCase
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

    /**
     * @var ArrayCache
     */
    private static $cache;

    public static function setUpBeforeClass(): void
    {
        self::resetConnection();

        self::$cache = new ArrayCache();

        $dbConnection = ConnectionFactory::resetDatabase(
            $GLOBALS['db_driver'],
            $GLOBALS['db_host'] ?? null,
            $GLOBALS['db_port'] ?? null,
            $GLOBALS['db_username'] ?? null,
            $GLOBALS['db_admin_username'] ?? null,
            $GLOBALS['db_password'] ?? null,
            $GLOBALS['db_name'] ?? null
        );

        self::$dbConnection = $dbConnection;

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
            self::$dbConnection = ConnectionFactory::createConnection(
                $GLOBALS['db_driver'],
                $GLOBALS['db_host'] ?? null,
                $GLOBALS['db_port'] ?? null,
                $GLOBALS['db_username'] ?? null,
                $GLOBALS['db_password'] ?? null,
                $GLOBALS['db_name'] ?? null
            );
        }
        return self::$dbConnection;
    }

    protected function onlyMySql()
    {
        if (!self::getConnection()->getDatabasePlatform() instanceof MySqlPlatform) {
            $this->markTestSkipped('MySQL specific test');
        }
    }

    protected function setUp(): void
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

    protected function getCache(): ArrayCache
    {
        return self::$cache;
    }

    protected function getConfiguration() : ConfigurationInterface
    {
        if ($this->configuration === null) {
            $this->configuration = new Configuration('TheCodingMachine\\TDBM\\Test\\Dao\\Bean', 'TheCodingMachine\\TDBM\\Test\\Dao', self::getConnection(), $this->getNamingStrategy(), $this->getCache(), null, null, [$this->getDummyGeneratorListener()]);
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

        $db = new TdbmFluidSchema($toSchema, new \TheCodingMachine\FluidSchema\DefaultNamingStrategy($connection->getDatabasePlatform()));

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
            ->column('modified_at')->datetime()->null()->index()
            ->column('order')->integer()->null();


        $db->table('contact')
            ->extends('person')
            ->column('email')->string(255)
            ->column('manager_id')->references('contact')->null();

        $db->table('users')
            ->addAnnotation('AddTrait', ['name'=>TestUserTrait::class], false)
            ->addAnnotation('AddTrait', ['name'=>TestOtherUserTrait::class, 'modifiers'=>['\\'.TestOtherUserTrait::class.'::method1 insteadof \\'.TestUserTrait::class, '\\'.TestUserTrait::class.'::method1 as method1renamed']], false)
            ->addAnnotation('AddTraitOnDao', ['name'=>TestUserDaoTrait::class], false)
            ->implementsInterface(TestUserInterface::class)
            ->implementsInterfaceOnDao(TestUserDaoInterface::class)
            ->extends('contact')
            ->column('login')->string(255)
            ->column('password')->string(255)->null()
            ->column('status')->string(10)->null()->default(null)
            ->column('country_id')->references('country')
            // Used to test generation for a column that starts with a digit
            ->then()->column('3d_view')->boolean()->default(true);

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

        $db->table('panda')
            ->extends('animal')
            ->column('weight')->float()->null();

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
            ->column('author_id')->references('users')->null()
            ->column('attachment')->blob()->null();

        $db->table('article2')
            ->column('id')->string(36)->primaryKey()->comment('@UUID v4')
            ->column('content')->string(255);

        $db->table('files')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment('@Autoincrement')
            ->column('file')->blob()
            ->column('md5')->string()->null()->comment("@ProtectedGetter\n@ProtectedSetter")
            ->column('article_id')->references('article')->null()->comment("@ProtectedGetter\n@ProtectedSetter\n@ProtectedOneToMany");

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

        // Tables using @Json annotations
        $db->table('accounts')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('name')->string();

        $db->table('nodes')
            ->column('id')->integer()->primaryKey()->autoIncrement()->comment("@JsonIgnore\n@Autoincrement")
            ->column('alias_id')->references('nodes')->null()->comment('@JsonRecursive')
            ->column('parent_id')->references('nodes')->null()->comment('@JsonInclude')
            ->column('root_id')->references('nodes')->null()->comment('@JsonIgnore')
            ->column('owner_id')->references('accounts')->null()->comment('@JsonFormat(property="name") @JsonInclude')
            ->column('name')->string()->comment('@JsonKey("basename")')
            ->column('size')->integer()->notNull()->default(0)->comment('@JsonFormat(unit=" o")')
            ->column('weight')->float()->null()->comment('@JsonFormat(decimals=2,unit="g")')
            ->column('created_at')->date()->null()->comment('@JsonFormat("Y-m-d")');

        $db->table('nodes_guests')
            ->column('node_id')->references('nodes')->comment('@JsonIgnore')
            ->column('guest_id')->references('accounts')->comment('@JsonKey("guests") @JsonFormat(method="getName")');

        $db->table('node_entries')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('node_id')->references('nodes')->comment('@JsonCollection("entries") @JsonFormat(property="entry")')
            ->column('entry')->string()->null();

        $db->table('artists')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('children')->array()->null() //used to test conflicts with autopivot
            ->column('name')->string();

        $db->table('albums')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('artist_id')->references('artists')->comment('@JsonCollection(key="discography")')
            ->column('account_id')->references('accounts')
            ->column('node_id')->references('nodes')->null()
            ->column('title')->string()
            ->then()->unique(['artist_id', 'account_id'])->unique(['artist_id', 'node_id']);

        $db->table('tracks')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('album_id')->references('albums')->comment('@JsonCollection @JsonRecursive')
            ->column('title')->string()
            ->column('duration')->time()->comment('@JsonFormat("H:i:s")');

        $db->table('featuring')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('track_id')->references('tracks')
            ->column('artist_id')->references('artists')->comment('@JsonKey("feat") @JsonInclude');

        $db->table('artists_relations') //used to test the auto pivot case
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('parent_id')->references('artists')
            ->column('child_id')->references('artists');

        $db->table('children') //used to test conflicts with autopivot
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('artist_id')->references('artists');

        $db->junctionTable('person', 'boats');

        if (!$connection->getDatabasePlatform() instanceof OraclePlatform) {
            $db->table('base_objects')
                ->column('id')->integer()->primaryKey()->autoIncrement()
                ->column('label')->string();
            $db->table('inherited_objects')
                ->column('id')->integer()->primaryKey()->autoIncrement()
                ->column('base_object_id')->references('base_objects')->unique()->comment('@JsonCollection');
        }

        $db->table('composite_fk_target_reference')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('label')->string();

        $targetTable = $db->table('composite_fk_target')
            ->column('id_1')->references('composite_fk_target_reference')
            ->column('id_2')->integer()
            ->then()->primaryKey(['id_1', 'id_2']);
        $db->table('composite_fk_source')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('fk_1')->integer()
            ->column('fk_2')->integer()
            ->then()->getDbalTable()->addForeignKeyConstraint($targetTable->getDbalTable(), [$connection->quoteIdentifier('fk_1'), $connection->quoteIdentifier('fk_2')], [$connection->quoteIdentifier('id_1'), $connection->quoteIdentifier('id_2')]);

        // Test case, the problem here is:
        // - `inheritance_agency` have an FK to `inheritance_society.**id_entity**`
        // - `inheritance_society` have an FK to `inheritance_entity.**id**`
        $db->table('inheritance_entity')
            ->column('id')->integer()->primaryKey()->autoIncrement();
        $db->table('inheritance_society')
            ->column('id_entity')->references('inheritance_entity')->primaryKey()
            ->then();
        $db->table('inheritance_agency')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('id_parent_society')->references('inheritance_society');

        $sqlStmts = $toSchema->getMigrateFromSql($fromSchema, $connection->getDatabasePlatform());

        foreach ($sqlStmts as $sqlStmt) {
            //echo $sqlStmt."\n";
            $connection->exec($sqlStmt);
        }

        // Let's generate computed columns
        if ($connection->getDatabasePlatform() instanceof MySqlPlatform && !self::isMariaDb($connection)) {
            $connection->exec('CREATE TABLE `players` (
               `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
               `player_and_games` JSON NOT NULL,
               `names_virtual` VARCHAR(20) GENERATED ALWAYS AS (`player_and_games` ->> \'$.name\') NOT NULL COMMENT \'@ReadOnly\',
               `animal_id` INT COMMENT \'@ReadOnly\',
               PRIMARY KEY (`id`),
               FOREIGN KEY (animal_id) REFERENCES animal(id)
            );
            ');
        }

        self::insert($connection, 'country', [
            'label' => 'France',
        ]);
        self::insert($connection, 'country', [
            'label' => 'uk',
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

        self::insert($connection, 'accounts', [
            'id' => 1,
            'name' => 'root'
        ]);
        self::insert($connection, 'accounts', [
            'id' => 2,
            'name' => 'user'
        ]);
        self::insert($connection, 'accounts', [
            'id' => 3,
            'name' => 'www'
        ]);
        self::insert($connection, 'nodes', [
            'id' => 1,
            'owner_id' => 1,
            'name' => '/',
            'created_at' => (new DateTime('last year'))->format('Y-m-d 00:00:00'),
        ]);
        self::insert($connection, 'nodes', [
            'id' => 2,
            'name' => 'private',
            'created_at' => (new DateTime('last year'))->format('Y-m-d 00:00:00'),
            'parent_id' => 1,
        ]);
        self::insert($connection, 'nodes', [
            'id' => 3,
            'name' => 'var',
            'created_at' => (new DateTime('last year'))->format('Y-m-d 00:00:00'),
            'parent_id' => 2,
        ]);
        self::insert($connection, 'nodes', [
            'id' => 4,
            'name' => 'var',
            'created_at' => (new DateTime('last year'))->format('Y-m-d 00:00:00'),
            'parent_id' => 1,
            'alias_id' => 3
        ]);
        self::insert($connection, 'nodes', [
            'id' => 5,
            'name' => 'www',
            'created_at' => (new DateTime('last week'))->format('Y-m-d 00:00:00'),
            'parent_id' => 4
        ]);
        self::insert($connection, 'nodes', [
            'id' => 6,
            'owner_id' => 2,
            'name' => 'index.html',
            'created_at' => (new DateTime('now'))->format('Y-m-d 00:00:00'),
            'size' => 512,
            'weight' => 42.5,
            'parent_id' => 5
        ]);
        self::insert($connection, 'nodes', [
            'id' => 7,
            'name' => 'index.html',
            'created_at' => (new DateTime('now'))->format('Y-m-d 00:00:00'),
            'alias_id' => 6,
            'parent_id' => 1
        ]);
        self::insert($connection, 'nodes', [
            'id' => 8,
            'name' => 'index.htm',
            'created_at' => (new DateTime('now'))->format('Y-m-d 00:00:00'),
            'alias_id' => 7,
            'parent_id' => 1
        ]);
        self::insert($connection, 'nodes_guests', [
            'node_id' => 6,
            'guest_id' => 1
        ]);
        self::insert($connection, 'nodes_guests', [
            'node_id' => 6,
            'guest_id' => 3
        ]);
        self::insert($connection, 'node_entries', [
            'node_id' => 6,
            'entry' => '<h1>'
        ]);
        self::insert($connection, 'node_entries', [
            'node_id' => 6,
            'entry' => 'Hello, World'
        ]);
        self::insert($connection, 'node_entries', [
            'node_id' => 6,
            'entry' => '</h1>'
        ]);


        self::insert($connection, 'artists', [
            'id' => 1,
            'name' => 'Pink Floyd'
        ]);
        self::insert($connection, 'artists', [
            'id' => 2,
            'name' => 'Roger Waters'
        ]);
        self::insert($connection, 'artists', [
            'id' => 3,
            'name' => 'David Gilmour'
        ]);
        self::insert($connection, 'albums', [
            'id' => 1,
            'artist_id' => 1,
            'account_id' => 1,
            'title' => 'Animals'
        ]);
        self::insert($connection, 'artists_relations', [
            'parent_id' => 1,
            'child_id' => 2
        ]);

        $timeType = Type::getType(Type::TIME_IMMUTABLE);

        self::insert($connection, 'tracks', [
            'album_id' => 1,
            'title' =>'Pigs on the Wing 1',
            // Note: Oracle does not have a TIME column type
            'duration' => $timeType->convertToDatabaseValue(new DateTimeImmutable('1970-01-01 00:01:25'), $connection->getDatabasePlatform()),
        ]);
        self::insert($connection, 'tracks', [
            'album_id' => 1,
            'title' => 'Dogs',
            'duration' => $timeType->convertToDatabaseValue(new DateTimeImmutable('1970-01-01 00:17:04'), $connection->getDatabasePlatform()),
        ]);
        self::insert($connection, 'tracks', [
            'album_id' => 1,
            'title' => 'Pigs (Three Different Ones)',
            'duration' => $timeType->convertToDatabaseValue(new DateTimeImmutable('1970-01-01 00:11:22'), $connection->getDatabasePlatform()),
        ]);
        self::insert($connection, 'tracks', [
            'album_id' => 1,
            'title' => 'Sheep',
            'duration' => $timeType->convertToDatabaseValue(new DateTimeImmutable('1970-01-01 00:10:24'), $connection->getDatabasePlatform()),
        ]);
        self::insert($connection, 'tracks', [
            'album_id' => 1,
            'title' => 'Pigs on the Wing 2',
            'duration' => $timeType->convertToDatabaseValue(new DateTimeImmutable('1970-01-01 00:01:26'), $connection->getDatabasePlatform()),
        ]);
        self::insert($connection, 'featuring', [
            'track_id' => 1,
            'artist_id' => 2
        ]);
        self::insert($connection, 'featuring', [
            'track_id' => 2,
            'artist_id' => 3
        ]);
        self::insert($connection, 'featuring', [
            'track_id' => 2,
            'artist_id' => 2
        ]);
        self::insert($connection, 'featuring', [
            'track_id' => 3,
            'artist_id' => 2
        ]);
        self::insert($connection, 'featuring', [
            'track_id' => 4,
            'artist_id' => 2
        ]);
        self::insert($connection, 'featuring', [
            'track_id' => 5,
            'artist_id' => 2
        ]);

        self::insert($connection, 'boats', [
            'name' => 'RoseBud',
            'anchorage_country' => 1,
            'current_country' => 1,
            'length' => '13.5',
        ]);

        self::insert($connection, 'person_boats', [
            'person_id' => 1,
            'boat_id' => 1,
        ]);
        self::insert($connection, 'composite_fk_target_reference', [
            'id' => 1,
            'label' => 'test'
        ]);
        self::insert($connection, 'composite_fk_target', [
            'id_1' => 1,
            'id_2' => 1
        ]);
        self::insert($connection, 'composite_fk_source', [
            'id' => 1,
            'fk_1' => 1,
            'fk_2' => 1
        ]);
    }

    public static function insert(Connection $connection, string $tableName, array $data): void
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

    protected static function isMariaDb(Connection $connection): bool
    {
        if (!$connection->getDatabasePlatform() instanceof MySqlPlatform) {
            return false;
        }
        $version = $connection->fetchColumn('SELECT VERSION()');
        return stripos($version, 'maria') !== false;
    }
}
