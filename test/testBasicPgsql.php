<?php
namespace Mouf\Database\TDBM;

require_once 'PHPUnit/Framework.php';

// Packages dependencies
$baseDir = dirname(__FILE__)."/../../../../..";
require_once $baseDir.'/plugins/database/dbconnection/1.0/DB_Column.php';
require_once $baseDir.'/plugins/database/dbconnection/1.0/DB_Table.php';
require_once $baseDir.'/plugins/database/dbconnection/1.0/DB_ConnectionSettingsInterface.php';
require_once $baseDir.'/plugins/database/dbconnection/1.0/ConnectionInterface.php';
require_once $baseDir.'/plugins/database/dbconnection/1.0/DB_Exception.php';
require_once $baseDir.'/plugins/database/dbconnection/1.0/Mouf_DBConnection.php';
require_once $baseDir.'/plugins/database/dbconnection/1.0/DB_MySqlConnection.php';
require_once $baseDir.'/plugins/database/dbconnection/1.0/DB_PgSqlConnection.php';
require_once $baseDir.'/plugins/database/dbconnection/1.0/DB_CachedConnection.php';
require_once $baseDir.'/plugins/database/tdbm/2.0/TDBMService.php';
require_once $baseDir.'/plugins/utils/cache/cache-interface/1.0/CacheInterface.php';
require_once $baseDir.'/plugins/utils/cache/session-cache/1.0/SessionCache.php';
require_once $baseDir.'/plugins/utils/log/log_interface/1.0/LogInterface.php';
require_once $baseDir.'/plugins/utils/log/errorlog_logger/1.0/ErrorLogLogger.php';


/*

$table = new DB_Table("users");
$table->addColumn(new DB_Column("id","INT",false,null,true,true));
$table->addColumn(new DB_Column("login","VARCHAR(255)"));
$table->addColumn(new DB_Column("password","VARCHAR(255)"));
$conn->createTable($table);
*/




class TdbmBasicPgsqlTest extends PHPUnit_Framework_TestCase 
{
	private $conn;
	private $tdbm;
	
	public function setUp() {
		// First step, let's create the database (or recreate the database) and fill it with test data.
		$error_log = new ErrorLogLogger();
		$error_log->level = ErrorLogLogger::$DEBUG;
		
		$this->conn = new DB_PgSqlConnection();
		$this->conn->host = "localhost";
		//$conn->dbname = "admindeo";
		$this->conn->user = "demo";
		$this->conn->password = "demo";
		$this->conn->dbname = "demo";
		$this->conn->connect();
		
		if ($this->conn->checkDatabaseExists("tdbmunittest")) {
			$this->conn->dropDatabase("tdbmunittest");
		}
		$this->conn->createDatabase("tdbmunittest");
		$this->conn->executeSqlFile("sql/tdbmunittest_pgsql.sql");
		
		// Now, let's initialize the TDBM service.
		$sessionCache = new SessionCache();
		$sessionCache->log = $error_log;
		
		$this->tdbm = new TDBMService();
		$this->tdbm->setCacheService($sessionCache);
		$this->tdbm->setConnection($this->conn);
		$this->tdbm->log = $error_log;
				
	}
	
	public function testInsertAndRetrieveData() {
	
		$user = $this->tdbm->getNewObject("users");
		$user->login = "admin";
		$user->password = "admin";
		$user->save();
		
		$users = $this->tdbm->getObjects("users");
		$this->assertTrue($users[0]->password == "admin");
		
		$role = $this->tdbm->getNewObject("roles");
		$role->name="admin";
		
		$user_role = $this->tdbm->getNewObject("users_roles");
		$user_role->user_id=$user->id;
		$user_role->role_id=$role->id;

		$roles = $this->tdbm->getObjects("roles");
		$this->assertTrue($roles[0]->name == "admin");

		$users = $this->tdbm->getObjects("users", new EqualFilter("roles", "name", "admin"));
		var_dump($users);
		$this->assertTrue(count($users)==1);
		
	}
	
} 

?>