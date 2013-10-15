<?php 
/**
 * This page generates the DAOS (via direct access)
 * 
 */
use Mouf\MoufManager;

use Mouf\Database\TDBM\Utils\TDBMDaoGenerator;

use Mouf\MoufUtils;

ini_set("display_errors", 1);
error_reporting(error_reporting() | E_ERROR);

require_once '../../../../mouf/Mouf.php';

// Note: checking rights is done after loading the required files because we need to open the session
// and only after can we check if it was not loaded before loading it ourselves...
MoufUtils::checkRights();

$tdbmServiceInstanceName = $_REQUEST["name"];
$tdbmService = MoufManager::getMoufManager()->getInstance($tdbmServiceInstanceName);

$daoFactoryClassName = $_REQUEST["daofactoryclassname"];

$sourcedirectory = $_REQUEST["sourcedirectory"];
$daonamespace = $_REQUEST["daonamespace"];
$beannamespace = $_REQUEST["beannamespace"]; 
$support = $_REQUEST["support"];
$storeInUtc = $_REQUEST["storeInUtc"];

$dbConnection = $tdbmService->dbConnection;
$daoGenerator = new TDBMDaoGenerator($dbConnection, $daoFactoryClassName, $sourcedirectory, $daonamespace, $beannamespace, $support, $storeInUtc);
$xml = $daoGenerator->generateAllDaosAndBeans();
echo $xml->asXml();

?>