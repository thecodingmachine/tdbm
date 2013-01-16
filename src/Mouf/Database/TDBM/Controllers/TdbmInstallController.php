<?php
namespace Mouf\Database\TDBM\Controllers;

use Mouf\MoufUtils;

use Mouf\Actions\InstallUtils;

use Mouf\MoufManager;

use Mouf\Html\HtmlElement\HtmlBlock;
use Mouf\Mvc\Splash\Controllers\Controller;

/**
 * The controller used in the TDBM install process.
 * 
 * @Component
 */
class TdbmInstallController extends Controller {
	
	/**
	 *
	 * @var HtmlBlock
	 */
	public $content;
	
	public $selfedit;
	
	/**
	 * The active MoufManager to be edited/viewed
	 *
	 * @var MoufManager
	 */
	public $moufManager;
	
	/**
	 * The template used by the main page for mouf.
	 *
	 * @Property
	 * @Compulsory
	 * @var TemplateInterface
	 */
	public $template;
	
	/**
	 * Displays the first install screen.
	 * 
	 * @Action
	 * @Logged
	 * @param string $selfedit If true, the name of the component must be a component from the Mouf framework itself (internal use only) 
	 */
	public function defaultAction($selfedit = "false") {
		$this->selfedit = $selfedit;
		
		if ($selfedit == "true") {
			$this->moufManager = MoufManager::getMoufManager();
		} else {
			$this->moufManager = MoufManager::getMoufManagerHiddenInstance();
		}
				
		$this->content->addFile(dirname(__FILE__)."/../../../../views/installStep1.php", $this);
		$this->template->toHtml();
	}

	/**
	 * Skips the install process.
	 * 
	 * @Action
	 * @Logged
	 * @param string $selfedit If true, the name of the component must be a component from the Mouf framework itself (internal use only)
	 */
	public function skip($selfedit = "false") {
		InstallUtils::continueInstall($selfedit == "true");
	}

	protected $daoNamespace;
	protected $beanNamespace;
	protected $sourceDirectory;
	protected $autoloadDetected;
	
	/**
	 * Displays the second install screen.
	 * 
	 * @Action
	 * @Logged
	 * @param string $selfedit If true, the name of the component must be a component from the Mouf framework itself (internal use only) 
	 */
	public function configure($selfedit = "false") {
		$this->selfedit = $selfedit;
		
		if ($selfedit == "true") {
			$this->moufManager = MoufManager::getMoufManager();
		} else {
			$this->moufManager = MoufManager::getMoufManagerHiddenInstance();
		}
		
		// Let's start by performing basic checks about the instances we assume to exist.
		if (!$this->moufManager->instanceExists("dbConnection")) {
			$this->displayErrorMsg("The TDBM install process assumes your database connection instance is already created, and that the name of this instance is 'dbConnection'. Could not find the 'dbConnection' instance.");
			return;
		}
		
		if (!$this->moufManager->instanceExists("noCacheService")) {
			$this->displayErrorMsg("The TDBM install process assumes that a cache instance named 'noCacheService' exists. Could not find the 'noCacheService' instance.");
			return;
		}
		
		$this->sourceDirectory = $this->moufManager->getVariable("tdbmDefaultSourceDirectory");
		$this->daoNamespace = $this->moufManager->getVariable("tdbmDefaultDaoNamespace");
		$this->beanNamespace = $this->moufManager->getVariable("tdbmDefaultBeanNamespace");
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
								
		$this->content->addFile(dirname(__FILE__)."/../../../../views/installStep2.php", $this);
		$this->template->toHtml();
	}
	
	/**
	 * This action generates the TDBM instance, then the DAOs and Beans. 
	 * 
	 * @Action
	 * @param string $name
	 * @param bool $selfedit
	 */
	public function generate($sourcedirectory, $daonamespace, $beannamespace, $keepSupport = 0, $selfedit="false") {
		$this->selfedit = $selfedit;
		
		if ($selfedit == "true") {
			$this->moufManager = MoufManager::getMoufManager();
		} else {
			$this->moufManager = MoufManager::getMoufManagerHiddenInstance();
		}
		
		if (!$this->moufManager->instanceExists("tdbmService")) {
			$this->moufManager->declareComponent("tdbmService", "Mouf\\Database\\TDBM\\TDBMService");
			$this->moufManager->bindComponentViaSetter("tdbmService", "setConnection", "dbConnection");
			$this->moufManager->bindComponentViaSetter("tdbmService", "setCacheService", "noCacheService");
		}
		
		$this->moufManager->rewriteMouf();
		
		TdbmController::generateDaos($this->moufManager, "tdbmService", $sourcedirectory, $daonamespace, $beannamespace, "DaoFactory", "daoFactory", $selfedit, $keepSupport);
				
		InstallUtils::continueInstall($selfedit == "true");
	}
	
	protected $errorMsg;
	
	private function displayErrorMsg($msg) {
		$this->errorMsg = $msg;
		$this->content->addFile(dirname(__FILE__)."/../../../../views/installError.php", $this);
		$this->template->toHtml();
	}
}