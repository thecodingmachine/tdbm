<?php
namespace Mouf\Database\TDBM\Controllers;

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
	
	protected $daoDirectory;
	protected $beanDirectory;
	protected $daoFactoryName;
	protected $daoFactoryInstanceName;
	
	/**
	 * Admin page used to display the DAO generation form.
	 *
	 * @Action
	 * //@Admin
	 */
	public function defaultAction($name, $selfedit="false") {
		$this->initController($name, $selfedit);
		
		$this->daoDirectory = $this->moufManager->getVariable("tdbmDefaultDaoDirectory");
		$this->beanDirectory = $this->moufManager->getVariable("tdbmDefaultBeanDirectory");
		$this->daoFactoryName = $this->moufManager->getVariable("tdbmDefaultDaoFactoryName");
		$this->daoFactoryInstanceName = $this->moufManager->getVariable("tdbmDefaultDaoFactoryInstanceName");
		if ($this->daoDirectory == null) {
			$this->daoDirectory = "dao";
		}
		if ($this->beanDirectory == null) {
			$this->beanDirectory = "dao/beans";
		}
		if ($this->daoFactoryName == null) {
			$this->daoFactoryName = "DaoFactory";
		}
		if ($this->daoFactoryInstanceName == null) {
			$this->daoFactoryInstanceName = "daoFactory";
		}
		
		$this->template->addContentFile(dirname(__FILE__)."/../views/tdbmGenerate.php", $this);
		$this->template->draw();
	}
	
	/**
	 * This action generates the DAOs and Beans for the TDBM service passed in parameter. 
	 * 
	 * @Action
	 * @param string $name
	 * @param bool $selfedit
	 */
	public function generate($name, $daodirectory, $beandirectory, $daofactoryclassname, $daofactoryinstancename, $keepSupport = 0,$selfedit="false") {
		$this->initController($name, $selfedit);

		self::generateDaos($this->moufManager, $name, $daodirectory, $beandirectory, $daofactoryclassname, $daofactoryinstancename, $selfedit, $keepSupport);
				
		// TODO: better: we should redirect to a screen that list the number of DAOs generated, etc...
		header("Location: ".ROOT_URL."mouf/instance/?name=".urlencode($name)."&selfedit=".$selfedit);
	}
	
	/**
	 * This function generates the DAOs and Beans for the TDBM service passed in parameter. 
	 * 
	 */
	public static function generateDaos(MoufManager $moufManager, $name, $daodirectory, $beandirectory, $daofactoryclassname, $daofactoryinstancename, $selfedit="false", $keepSupport = null) {
		$moufManager->setVariable("tdbmDefaultDaoDirectory", $daodirectory);
		$moufManager->setVariable("tdbmDefaultBeanDirectory", $beandirectory);
		$moufManager->setVariable("tdbmDefaultDaoFactoryName", $daofactoryclassname);
		$moufManager->setVariable("tdbmDefaultDaoFactoryInstanceName", $daofactoryinstancename);
		
		// Remove first and last slash in directories.
		if (strpos($daodirectory, "/") === 0 || strpos($daodirectory, "\\") === 0) {
			$daodirectory = substr($daodirectory, 1);
		}
		if (strpos($daodirectory, "/") === strlen($daodirectory)-1 || strpos($daodirectory, "\\") === strlen($daodirectory)-1) {
			$daodirectory = substr($daodirectory, 0, strlen($daodirectory)-1);
		}
		if (strpos($beandirectory, "/") === 0 || strpos($beandirectory, "\\") === 0) {
			$beandirectory = substr($beandirectory, 1);
		}
		if (strpos($beandirectory, "/") === strlen($beandirectory)-1 || strpos($beandirectory, "\\") === strlen($beandirectory)-1) {
			$beandirectory = substr($beandirectory, 0, strlen($beandirectory)-1);
		}
		
		
		
		$url = MoufReflectionProxy::getLocalUrlToProject()."plugins/database/tdbm/2.3/generateDaos.php?name=".urlencode($name)."&selfedit=".$selfedit."&daofactoryclassname=".urlencode($daofactoryclassname)."&daodirectory=".urlencode($daodirectory)."&beandirectory=".urlencode($beandirectory)."&support=".urlencode($keepSupport);
		$response = self::performRequest($url);
		
		/*if (trim($response) != "") {
			throw new Exception($response);
		}*/
		
		$xmlRoot = simplexml_load_string($response);
		
		if ($xmlRoot == null) {
			throw new Exception("An error occured while retrieving message: ".$response);
		}

		$moufManager->declareComponent($daofactoryinstancename, $daofactoryclassname, false, MoufManager::DECLARE_ON_EXIST_KEEP_INCOMING_LINKS);
		
		foreach ($xmlRoot->table as $table) {
			$daoName = TDBMDaoGenerator::getDaoNameFromTableName($table);
			$moufManager->addRegisteredComponentFile($daodirectory."/".$daoName.".php");

			$instanceName = TDBMDaoGenerator::toVariableName($daoName);
			if (!$moufManager->instanceExists($instanceName)) {
				$moufManager->declareComponent($instanceName, $daoName);
			}
			$moufManager->bindComponentViaSetter($instanceName, "setTdbmService", $name);
			$moufManager->bindComponentViaSetter($daofactoryinstancename, "set".$daoName, $instanceName);
		}
		
		$moufManager->addRegisteredComponentFile($daodirectory."/".$daofactoryclassname.".php");
		
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