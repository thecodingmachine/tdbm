<?php 
namespace Mouf\Database\TDBM;

require_once dirname(__FILE__).'/../../../../../Mouf.php';

$conn = new DB_MySqlConnection();
$conn->host = "localhost";
$conn->dbname = "sticker";
$conn->user = "root";
$conn->connect();

$sessionCache = new SessionCache();
$sessionCache->log = new ErrorLogLogger();

TDBMObject::setCacheService($sessionCache);
TDBMObject::setConnection($conn);
$test = TDBMObject::getObjects("user", new DBM_EqualFilter("user", "login", "admin"));

//var_dump($conn->getAll("SELECT * FROM users"));

//var_dump($conn->getOne("SELECT count(1) FROM users"));

var_dump($test->login);

?>