<?php
namespace Mouf\Database\TDBM\Controllers;

use Mouf\Composer\ClassNameMapper;
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
	protected $autoloadDetected;
	protected $keepSupport;
	protected $castDatesToDateTime;
	protected $storeInUtc;
	
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
		
		$this->daoNamespace = $this->moufManager->getVariable("tdbmDefaultDaoNamespace_tdbmService");
		$this->beanNamespace = $this->moufManager->getVariable("tdbmDefaultBeanNamespace_tdbmService");
		
		if ($this->daoNamespace == null && $this->beanNamespace == null) {
            $classNameMapper = ClassNameMapper::createFromComposerFile(__DIR__.'/../../../../../../../../composer.json');

            $autoloadNamespaces = $classNameMapper->getManagedNamespaces();
			if ($autoloadNamespaces) {
				$this->autoloadDetected = true;
                $rootNamespace = $autoloadNamespaces[0];
				$this->daoNamespace = $rootNamespace."Dao";
				$this->beanNamespace = $rootNamespace."Dao\\Bean";
			} else {
				$this->autoloadDetected = false;
				$this->daoNamespace = "YourApplication\\Dao";
				$this->beanNamespace = "YourApplication\\Dao\\Bean";
			}			
		} else {
			$this->autoloadDetected = true;
		}
								
		$this->content->addFile(dirname(__FILE__)."/../../../../views/installStep2.php", $this);
		$this->template->toHtml();
	}

	/**
	 * This action generates the TDBM instance, then the DAOs and Beans.
	 *
	 * @Action
	 * @param string $daonamespace
	 * @param string $beannamespace
	 * @param int $keepSupport
	 * @param int $storeInUtc
	 * @param int $castDatesToDateTime
	 * @param string $selfedit
	 * @throws \Mouf\MoufException
	 */
    public function generate($daonamespace, $beannamespace, $keepSupport = 0, $storeInUtc = 0, $castDatesToDateTime = 1, $selfedit="false") {
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
		
		TdbmController::generateDaos($this->moufManager, "tdbmService", $daonamespace, $beannamespace, "DaoFactory", "daoFactory", $selfedit, $keepSupport, $storeInUtc, $castDatesToDateTime);
				
		InstallUtils::continueInstall($selfedit == "true");
	}
	
	protected $errorMsg;
	
	private function displayErrorMsg($msg) {
		$this->errorMsg = $msg;
		$this->content->addFile(dirname(__FILE__)."/../../../../views/installError.php", $this);
		$this->template->toHtml();
	}
}