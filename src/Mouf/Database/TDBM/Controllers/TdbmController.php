<?php
namespace Mouf\Database\TDBM\Controllers;

use Mouf\Controllers\AbstractMoufInstanceController;

use Mouf\Database\TDBM\Utils\TDBMDaoGenerator;

use Mouf\MoufManager;

use Mouf\Mvc\Splash\Controllers\Controller;

use Mouf\Reflection\MoufReflectionProxy;

use Mouf\Html\HtmlElement\HtmlBlock;

/**
 * The controller to generate automatically the Beans, Daos, etc...
 * Sweet!
 * 
 * @Component
 */
class TdbmController extends AbstractMoufInstanceController {
	
	/**
	 *
	 * @var HtmlBlock
	 */
	public $content;
	
	protected $sourceDirectory;
	protected $daoNamespace;
	protected $beanNamespace;
	protected $daoFactoryName;
	protected $daoFactoryInstanceName;
	protected $autoloadDetected;
	
	/**
	 * Admin page used to display the DAO generation form.
	 *
	 * @Action
	 * //@Admin
	 */
	public function defaultAction($name, $selfedit="false") {
		$this->initController($name, $selfedit);
		
		$this->sourceDirectory = $this->moufManager->getVariable("tdbmDefaultSourceDirectory");
		$this->daoNamespace = $this->moufManager->getVariable("tdbmDefaultDaoNamespace");
		$this->beanNamespace = $this->moufManager->getVariable("tdbmDefaultBeanNamespace");
		$this->daoFactoryName = $this->moufManager->getVariable("tdbmDefaultDaoFactoryName");
		$this->daoFactoryInstanceName = $this->moufManager->getVariable("tdbmDefaultDaoFactoryInstanceName");
		
		if ($this->sourceDirectory == null && $this->daoNamespace == null && $this->beanNamespace == null) {
			$autoloadNamespaces = MoufUtils::getAutoloadNamespaces();
			if ($autoloadNamespaces) {
				$this->autoloadDetected = true;
				$rootNamespace = $autoloadNamespaces[0]['namespace'].'\\';
				$this->sourceDirectory = $autoloadNamespaces[0]['directory'];
				$this->daoNamespace = $rootNamespace."Dao";
				$this->beanNamespace = $rootNamespace."Dao\\Bean";
			} else {
				$this->autoloadDetected = false;
				$this->sourceDirectory = "src/";
				$this->daoNamespace = "YourApplication\\Dao";
				$this->beanNamespace = "YourApplication\\Dao\\Bean";
			}
						
		}
		
		$this->content->addFile(dirname(__FILE__)."/../../../../views/tdbmGenerate.php", $this);
		$this->template->toHtml();
	}
	
	/**
	 * This action generates the DAOs and Beans for the TDBM service passed in parameter. 
	 * 
	 * @Action
	 * @param string $name
	 * @param bool $selfedit
	 */
	public function generate($name, $sourcedirectory, $daonamespace, $beannamespace, $daofactoryclassname, $daofactoryinstancename, $keepSupport = 0,$selfedit="false") {
		$this->initController($name, $selfedit);

		self::generateDaos($this->moufManager, $name, $sourcedirectory, $daonamespace, $beannamespace, $daofactoryclassname, $daofactoryinstancename, $selfedit, $keepSupport);
				
		// TODO: better: we should redirect to a screen that list the number of DAOs generated, etc...
		header("Location: ".ROOT_URL."ajaxinstance/?name=".urlencode($name)."&selfedit=".$selfedit);
	}
	
	/**
	 * This function generates the DAOs and Beans for the TDBM service passed in parameter. 
	 * 
	 */
	public static function generateDaos(MoufManager $moufManager, $name, $sourcedirectory, $daonamespace, $beannamespace, $daofactoryclassname, $daofactoryinstancename, $selfedit="false", $keepSupport = null) {
		$moufManager->setVariable("tdbmDefaultSourceDirectory", $sourcedirectory);
		$moufManager->setVariable("tdbmDefaultDaoNamespace", $daonamespace);
		$moufManager->setVariable("tdbmDefaultBeanNamespace", $beannamespace);
		$moufManager->setVariable("tdbmDefaultDaoFactoryName", $daofactoryclassname);
		$moufManager->setVariable("tdbmDefaultDaoFactoryInstanceName", $daofactoryinstancename);
		
		// Remove first and last slash in namespace.
		if (strpos($daonamespace, "\\") === 0) {
			$daonamespace = substr($daonamespace, 1);
		}
		if (strpos($daonamespace, "\\") === strlen($daonamespace)-1) {
			$daonamespace = substr($daonamespace, 0, strlen($daonamespace)-1);
		}
		if (strpos($beannamespace, "\\") === 0) {
			$beannamespace = substr($beannamespace, 1);
		}
		if (strpos($beannamespace, "\\") === strlen($beannamespace)-1) {
			$beannamespace = substr($beannamespace, 0, strlen($beannamespace)-1);
		}
		
		
		
		$url = MoufReflectionProxy::getLocalUrlToProject()."../database.tdbm/src/generateDaos.php?name=".urlencode($name)."&selfedit=".$selfedit."&sourcedirectory=".urlencode($sourcedirectory)."&daofactoryclassname=".urlencode($daofactoryclassname)."&daonamespace=".urlencode($daonamespace)."&beannamespace=".urlencode($beannamespace)."&support=".urlencode($keepSupport);
		$response = self::performRequest($url);
		
		/*if (trim($response) != "") {
			throw new Exception($response);
		}*/
		
		$xmlRoot = simplexml_load_string($response);
		
		if ($xmlRoot == null) {
			throw new \Exception("An error occured while retrieving message: ".$response);
		}

		$moufManager->declareComponent($daofactoryinstancename, $daonamespace."\\".$daofactoryclassname, false, MoufManager::DECLARE_ON_EXIST_KEEP_INCOMING_LINKS);
		
		foreach ($xmlRoot->table as $table) {
			$daoName = TDBMDaoGenerator::getDaoNameFromTableName($table);
			//$moufManager->addRegisteredComponentFile($daodirectory."/".$daoName.".php");

			$instanceName = TDBMDaoGenerator::toVariableName($daoName);
			if (!$moufManager->instanceExists($instanceName)) {
				$moufManager->declareComponent($instanceName, $daonamespace."\\".$daoName);
			}
			$moufManager->bindComponentViaSetter($instanceName, "setTdbmService", $name);
			$moufManager->bindComponentViaSetter($daofactoryinstancename, "set".$daoName, $instanceName);
		}
		
		//$moufManager->addRegisteredComponentFile($daodirectory."/".$daofactoryclassname.".php");
		
		$moufManager->rewriteMouf();
	}
	
	private static function performRequest($url) {
		// preparation de l'envoi
		$ch = curl_init();
				
		curl_setopt( $ch, CURLOPT_URL, $url);
		
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt( $ch, CURLOPT_POST, FALSE );
		
		if( curl_error($ch) ) { 
			throw new Exception("TODO: texte de l'erreur curl");
		} else {
			$response = curl_exec( $ch );
		}
		curl_close( $ch );
		
		return $response;
	}
	
}