<?php
namespace Mouf\Database\TDBM\Controllers;

use Mouf\Composer\ClassNameMapper;
use Mouf\Controllers\AbstractMoufInstanceController;

use Mouf\Database\TDBM\Utils\TDBMDaoGenerator;

use Mouf\MoufManager;

use Mouf\Reflection\MoufReflectionProxy;

use Mouf\MoufUtils;
use Mouf\InstanceProxy;

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
	
	protected $daoNamespace;
	protected $beanNamespace;
	protected $daoFactoryName;
	protected $daoFactoryInstanceName;
	protected $autoloadDetected;
	protected $keepSupport;
	protected $storeInUtc;
	
	/**
	 * Admin page used to display the DAO generation form.
	 *
	 * @Action
	 * //@Admin
	 */
	public function defaultAction($name, $selfedit="false") {
		$this->initController($name, $selfedit);
		
		// Fill variables
		if ($this->moufManager->getVariable("tdbmDefaultSourceDirectory_".$name) != null) {
			$this->daoNamespace = $this->moufManager->getVariable("tdbmDefaultDaoNamespace_".$name);
			$this->beanNamespace = $this->moufManager->getVariable("tdbmDefaultBeanNamespace_".$name);
			$this->daoFactoryName = $this->moufManager->getVariable("tdbmDefaultDaoFactoryName_".$name);
			$this->daoFactoryInstanceName = $this->moufManager->getVariable("tdbmDefaultDaoFactoryInstanceName_".$name);
			$this->keepSupport = $this->moufManager->getVariable("tdbmDefaultKeepSupport_".$name);
			$this->storeInUtc = $this->moufManager->getVariable("tdbmDefaultStoreInUtc_".$name);
		} else {
			$this->daoNamespace = $this->moufManager->getVariable("tdbmDefaultDaoNamespace");
			$this->beanNamespace = $this->moufManager->getVariable("tdbmDefaultBeanNamespace");
			$this->daoFactoryName = $this->moufManager->getVariable("tdbmDefaultDaoFactoryName");
			$this->daoFactoryInstanceName = $this->moufManager->getVariable("tdbmDefaultDaoFactoryInstanceName");
			$this->keepSupport = $this->moufManager->getVariable("tdbmDefaultKeepSupport");
			$this->storeInUtc = $this->moufManager->getVariable("tdbmDefaultStoreInUtc");
		}
				
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
	public function generate($name, $daonamespace, $beannamespace, $daofactoryclassname, $daofactoryinstancename, $keepSupport = 0, $storeInUtc = 0,$selfedit="false") {
		$this->initController($name, $selfedit);

		self::generateDaos($this->moufManager, $name, $daonamespace, $beannamespace, $daofactoryclassname, $daofactoryinstancename, $selfedit, $keepSupport, $storeInUtc);

		// TODO: better: we should redirect to a screen that list the number of DAOs generated, etc...
		header("Location: ".ROOT_URL."ajaxinstance/?name=".urlencode($name)."&selfedit=".$selfedit);
	}
	
	/**
	 * This function generates the DAOs and Beans for the TDBM service passed in parameter. 
	 * 
	 */
	public static function generateDaos(MoufManager $moufManager, $name, $daonamespace, $beannamespace, $daofactoryclassname, $daofactoryinstancename, $selfedit="false", $keepSupport = null, $storeInUtc = null) {
		$moufManager->setVariable("tdbmDefaultDaoNamespace_".$name, $daonamespace);
		$moufManager->setVariable("tdbmDefaultBeanNamespace_".$name, $beannamespace);
		$moufManager->setVariable("tdbmDefaultDaoFactoryName_".$name, $daofactoryclassname);
		$moufManager->setVariable("tdbmDefaultDaoFactoryInstanceName_".$name, $daofactoryinstancename);
		$moufManager->setVariable("tdbmDefaultKeepSupport_".$name, $keepSupport);
		$moufManager->setVariable("tdbmDefaultStoreInUtc_".$name, $storeInUtc);
		
		// In case of instance renaming, let's use the last used settings
		$moufManager->setVariable("tdbmDefaultDaoNamespace", $daonamespace);
		$moufManager->setVariable("tdbmDefaultBeanNamespace", $beannamespace);
		$moufManager->setVariable("tdbmDefaultDaoFactoryName", $daofactoryclassname);
		$moufManager->setVariable("tdbmDefaultDaoFactoryInstanceName", $daofactoryinstancename);
		$moufManager->setVariable("tdbmDefaultKeepSupport", $keepSupport);
		$moufManager->setVariable("tdbmDefaultStoreInUtc", $storeInUtc);
		
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
		
		$tdbmService = new InstanceProxy($name);
		/* @var $tdbmService TDBMService */
        $tables = $tdbmService->generateAllDaosAndBeans($daofactoryclassname, $daonamespace, $beannamespace, $keepSupport, $storeInUtc);


		$moufManager->declareComponent($daofactoryinstancename, $daonamespace."\\".$daofactoryclassname, false, MoufManager::DECLARE_ON_EXIST_KEEP_INCOMING_LINKS);

		$tableToBeanMap = [];

		foreach ($tables as $table) {
			$daoName = TDBMDaoGenerator::getDaoNameFromTableName($table);
		
			$instanceName = TDBMDaoGenerator::toVariableName($daoName);
			if (!$moufManager->instanceExists($instanceName)) {
				$moufManager->declareComponent($instanceName, $daonamespace."\\".$daoName);
			}
			$moufManager->bindComponentViaSetter($instanceName, "setTdbmService", $name);
			$moufManager->bindComponentViaSetter($daofactoryinstancename, "set".$daoName, $instanceName);

			$tableToBeanMap[$table] = $beannamespace . "\\" . TDBMDaoGenerator::getBeanNameFromTableName($table);
		}
		$tdbmServiceDescriptor = $moufManager->getInstanceDescriptor($name);
		$tdbmServiceDescriptor->getSetterProperty("setTableToBeanMap")->setValue($tableToBeanMap);
		$moufManager->rewriteMouf();
	}
}