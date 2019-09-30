<?php


namespace TheCodingMachine\TDBM\Performance;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use TheCodingMachine\FluidSchema\TdbmFluidSchema;
use TheCodingMachine\TDBM\Configuration;
use TheCodingMachine\TDBM\ConfigurationInterface;
use TheCodingMachine\TDBM\ConnectionFactory;
use TheCodingMachine\TDBM\DummyGeneratorListener;
use TheCodingMachine\TDBM\TDBMAbstractServiceTest;
use TheCodingMachine\TDBM\TDBMSchemaAnalyzer;
use TheCodingMachine\TDBM\TDBMService;
use TheCodingMachine\TDBM\Test\Dao\UserDao;
use TheCodingMachine\TDBM\Utils\PathFinder\PathFinder;
use TheCodingMachine\TDBM\Utils\TDBMDaoGenerator;
use function dirname;
use function getenv;
use function glob;
use function is_dir;
use function is_file;
use function rmdir;
use function rtrim;
use function unlink;

/**
 * @BeforeClassMethods({"initDatabase"})
 */
class ManyToOneBench
{
    public static function initDatabase(): void
    {
        $dbConnection = ConnectionFactory::resetDatabase(
            getenv('DB_DRIVER') ?: null,
            getenv('DB_HOST') ?: null,
            getenv('DB_PORT') ?: null,
            getenv('DB_USERNAME') ?: null,
            getenv('DB_ADMIN_USERNAME') ?: null,
            getenv('DB_PASSWORD') ?: null,
            getenv('DB_NAME') ?: null
        );

        self::initSchema($dbConnection);

        self::generateDaosAndBeans($dbConnection);
    }

    private static function initSchema(Connection $connection): void
    {
        $fromSchema = $connection->getSchemaManager()->createSchema();
        $toSchema = clone $fromSchema;

        $db = new TdbmFluidSchema($toSchema, new \TheCodingMachine\FluidSchema\DefaultNamingStrategy($connection->getDatabasePlatform()));

        $db->table('countries')
            ->column('id')->integer()->primaryKey()
            ->column('label')->string(255)->unique();

        $db->table('users')
            ->column('id')->integer()->primaryKey()
            ->column('name')->string(255)
            ->column('country_id')->references('countries');

        $sqlStmts = $toSchema->getMigrateFromSql($fromSchema, $connection->getDatabasePlatform());

        foreach ($sqlStmts as $sqlStmt) {
            $connection->exec($sqlStmt);
        }

        for ($i = 1; $i<200; $i++) {
            TDBMAbstractServiceTest::insert($connection, 'countries', [
                'id' => $i,
                'label' => 'Country '.$i,
            ]);
        }

        for ($i = 1; $i<1000; $i++) {
            TDBMAbstractServiceTest::insert($connection, 'users', [
                'id' => $i,
                'name' => 'User '.$i,
                'country_id' => ($i%199) +1,
            ]);
        }
    }

    private static function generateDaosAndBeans(Connection $connection): void
    {
        $schemaManager = $connection->getSchemaManager();
        $schemaAnalyzer = new SchemaAnalyzer($schemaManager);
        $tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($connection, new ArrayCache(), $schemaAnalyzer, Configuration::getDefaultLockFilePath());
        $tdbmDaoGenerator = new TDBMDaoGenerator(self::createConfiguration(), $tdbmSchemaAnalyzer);
        $rootPath = __DIR__ . '/../';
        self::recursiveDelete(__DIR__. '/../../src/Test/Dao/');

        $tdbmDaoGenerator->generateAllDaosAndBeans();
    }

    /**
     * Delete a file or recursively delete a directory.
     *
     * @param string $str Path to file or directory
     * @return bool
     */
    private static function recursiveDelete(string $str): bool
    {
        if (is_file($str)) {
            return @unlink($str);
        } elseif (is_dir($str)) {
            $scan = glob(rtrim($str, '/') . '/*');
            foreach ($scan as $index => $path) {
                self::recursiveDelete($path);
            }

            return @rmdir($str);
        }
        return false;
    }

    private static function getConnection(): Connection
    {
        return ConnectionFactory::createConnection(
            getenv('DB_DRIVER') ?: null,
            getenv('DB_HOST') ?: null,
            getenv('DB_PORT') ?: null,
            getenv('DB_USERNAME') ?: null,
            getenv('DB_PASSWORD') ?: null,
            getenv('DB_NAME') ?: null
        );
    }

    protected function getTdbmService(): TDBMService
    {
        return new TDBMService($this->getConfiguration());
    }

    private static $cache;

    protected static function getCache(): ArrayCache
    {
        if (self::$cache === null) {
            self::$cache = new ArrayCache();
        }
        return self::$cache;
    }

    private static function createConfiguration(): ConfigurationInterface
    {
        $configuration = new Configuration('TheCodingMachine\\TDBM\\Test\\Dao\\Bean', 'TheCodingMachine\\TDBM\\Test\\Dao', self::getConnection(), null, self::getCache(), null, null, []);
        $configuration->setPathFinder(new PathFinder(null, dirname(__DIR__, 5)));
        return $configuration;
    }

    /**
     * @var ConfigurationInterface
     */
    private $configuration;

    protected function getConfiguration() : ConfigurationInterface
    {
        if ($this->configuration === null) {
            return self::createConfiguration();
        }
        return $this->configuration;
    }

    /**
     * @Iterations(10)
     */
    public function benchManyToOne(): void
    {
        $tdbmService = $this->getTdbmService();
        $userDao = new UserDao($tdbmService);
        foreach ($userDao->findAll() as $user) {
            $label = $user->getCountry()->getLabel();
        }
    }
}
