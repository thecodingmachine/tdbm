<?php 
/**
 * This page generates the DAOS (via direct access)
 * 
 */
ini_set("display_errors", 1);
error_reporting(error_reporting() | E_ERROR);

require_once 'utils/dao_generator.php';

if (!isset($_REQUEST["selfedit"]) || $_REQUEST["selfedit"]!="true") {
	require_once '../../../../Mouf.php';
} else {
	require_once '../../../../mouf/MoufManager.php';
	MoufManager::initMoufManager();
	require_once '../../../../MoufUniversalParameters.php';
	require_once '../../../../mouf/MoufAdmin.php';
}

// Note: checking rights is done after loading the required files because we need to open the session
// and only after can we check if it was not loaded before loading it ourselves...
require_once '../../../../mouf/direct/utils/check_rights.php';


$tdbmServiceInstanceName = $_REQUEST["name"];
$tdbmService = MoufManager::getMoufManager()->getInstance($tdbmServiceInstanceName);

$daoFactoryClassName = $_REQUEST["daofactoryclassname"];

$daodirectory = $_REQUEST["daodirectory"];
$beandirectory = $_REQUEST["beandirectory"]; 
$support = isset($_REQUEST["support"]); 

$dbConnection = $tdbmService->dbConnection;
$daoGenerator = new TDBMDaoGenerator($dbConnection, $daoFactoryClassName, $daodirectory, $beandirectory, $support);
$xml = $daoGenerator->generateAllDaosAndBeans();
echo $xml->asXml();

?>