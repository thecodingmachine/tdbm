<?php
namespace Mouf\Database\TDBM\Controllers;

use Mouf\Html\HtmlElement\HtmlBlock;

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
				
		$this->template->addContentFile(dirname(__FILE__)."/../views/installStep1.php", $this);
		$this->template->draw();
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

	protected $daoDirectory;
	protected $beanDirectory;
	
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
		
		$this->daoDirectory = $this->moufManager->getVariable("tdbmDefaultDaoDirectory");
		$this->beanDirectory = $this->moufManager->getVariable("tdbmDefaultBeanDirectory");
		if ($this->daoDirectory == null) {
			$this->daoDirectory = "dao";
		}
		if ($this->beanDirectory == null) {
			$this->beanDirectory = "dao/beans";
		}
						
		$this->template->addContentFile(dirname(__FILE__)."/../views/installStep2.php", $this);
		$this->template->draw();
	}
	
	/**
	 * This action generates the TDBM instance, then the DAOs and Beans. 
	 * 
	 * @Action
	 * @param string $name
	 * @param bool $selfedit
	 */
	public function generate($daodirectory, $beandirectory, $keepSupport = 0, $selfedit="false") {
		$this->selfedit = $selfedit;
		
		if ($selfedit == "true") {
			$this->moufManager = MoufManager::getMoufManager();
		} else {
			$this->moufManager = MoufManager::getMoufManagerHiddenInstance();
		}
		
		if (!$this->moufManager->instanceExists("tdbmService")) {
			$this->moufManager->declareComponent("tdbmService", "TDBMService");
			$this->moufManager->bindComponentViaSetter("tdbmService", "setConnection", "dbConnection");
			$this->moufManager->bindComponentViaSetter("tdbmService", "setCacheService", "noCacheService");
		}
		
		$this->moufManager->rewriteMouf();
		
		TdbmController::generateDaos($this->moufManager, "tdbmService", $daodirectory, $beandirectory, "DaoFactory", "daoFactory", $selfedit, $keepSupport);
				
		InstallUtils::continueInstall($selfedit == "true");
	}
	
	protected $errorMsg;
	
	private function displayErrorMsg($msg) {
		$this->errorMsg = $msg;
		$this->template->addContentFile(dirname(__FILE__)."/../views/installError.php", $this);
		$this->template->draw();
	}
}