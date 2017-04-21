<?php

namespace Mouf\Database\TDBM\Controllers;

use Mouf\Composer\ClassNameMapper;
use Mouf\Controllers\AbstractMoufInstanceController;
use Mouf\Database\TDBM\TDBMService;
use Mouf\Database\TDBM\Utils\TDBMDaoGenerator;
use Mouf\Html\HtmlElement\HtmlBlock;
use Mouf\MoufManager;
use Mouf\InstanceProxy;

/**
 * The controller to generate automatically the Beans, Daos, etc...
 * Sweet!
 *
 * @Component
 */
class TdbmController extends AbstractMoufInstanceController
{
    /**
     * @var HtmlBlock
     */
    public $content;

    protected $daoNamespace;
    protected $beanNamespace;
    protected $daoFactoryInstanceName;
    protected $autoloadDetected;
    protected $storeInUtc;
    protected $useCustomComposer;
    protected $composerFile;

    /**
     * Admin page used to display the DAO generation form.
     *
     * @Action
     */
    public function defaultAction($name, $selfedit = 'false')
    {
        $this->initController($name, $selfedit);

        // Fill variables
        $this->daoNamespace = self::getFromConfiguration($this->moufManager, $name, 'daoNamespace');
        $this->beanNamespace = self::getFromConfiguration($this->moufManager, $name, 'beanNamespace');
        $this->daoFactoryInstanceName = self::getFromConfiguration($this->moufManager, $name, 'daoFactoryInstanceName');
        $this->storeInUtc = self::getFromConfiguration($this->moufManager, $name, 'storeInUtc');
        $this->composerFile = self::getFromConfiguration($this->moufManager, $name, 'customComposerFile');
        $this->useCustomComposer = $this->composerFile ? true : false;

        if ($this->daoNamespace == null && $this->beanNamespace == null) {
            $classNameMapper = ClassNameMapper::createFromComposerFile(__DIR__.'/../../../../../../../../composer.json');

            $autoloadNamespaces = $classNameMapper->getManagedNamespaces();
            if ($autoloadNamespaces) {
                $this->autoloadDetected = true;
                $rootNamespace = $autoloadNamespaces[0];
                $this->daoNamespace = $rootNamespace.'Dao';
                $this->beanNamespace = $rootNamespace.'Dao\\Bean';
            } else {
                $this->autoloadDetected = false;
                $this->daoNamespace = 'YourApplication\\Dao';
                $this->beanNamespace = 'YourApplication\\Dao\\Bean';
            }
        } else {
            $this->autoloadDetected = true;
        }

        $this->content->addFile(__DIR__.'/../../../../views/tdbmGenerate.php', $this);
        $this->template->toHtml();
    }

    /**
     * This action generates the DAOs and Beans for the TDBM service passed in parameter.
     *
     * @Action
     *
     * @param string $name
     * @param bool   $selfedit
     */
    public function generate($name, $daonamespace, $beannamespace, $daofactoryinstancename, $storeInUtc = 0, $selfedit = 'false', $useCustomComposer = false, $composerFile = '')
    {
        $this->initController($name, $selfedit);

        self::generateDaos($this->moufManager, $name, $daonamespace, $beannamespace, $daofactoryinstancename, $selfedit, $storeInUtc, $useCustomComposer, $composerFile);

        // TODO: better: we should redirect to a screen that list the number of DAOs generated, etc...
        header('Location: '.ROOT_URL.'ajaxinstance/?name='.urlencode($name).'&selfedit='.$selfedit);
    }

    /**
     * This function generates the DAOs and Beans for the TDBM service passed in parameter.
     *
     * @param MoufManager $moufManager
     * @param string      $name
     * @param string      $daonamespace
     * @param string      $beannamespace
     * @param string      $daofactoryclassname
     * @param string      $daofactoryinstancename
     * @param string      $selfedit
     * @param bool        $storeInUtc
     *
     * @throws \Mouf\MoufException
     */
    public static function generateDaos(MoufManager $moufManager, $name, $daonamespace, $beannamespace, $daofactoryinstancename, $selfedit = 'false', $storeInUtc = null, $useCustomComposer = null, $composerFile = null)
    {
        self::setInConfiguration($moufManager, $name, 'daoNamespace', $daonamespace);
        self::setInConfiguration($moufManager, $name, 'beanNamespace', $beannamespace);
        self::setInConfiguration($moufManager, $name, 'daoFactoryInstanceName', $daofactoryinstancename);
        self::setInConfiguration($moufManager, $name, 'storeInUtc', $storeInUtc);
        if ($useCustomComposer) {
            self::setInConfiguration($moufManager, $name, 'customComposerFile', $composerFile);
        } else {
            self::setInConfiguration($moufManager, $name, 'customComposerFile', null);
        }
        // Let's rewrite before calling the DAO generator
        $moufManager->rewriteMouf();


        $tdbmService = new InstanceProxy($name);
        /* @var $tdbmService TDBMService */
        $tdbmService->generateAllDaosAndBeans($storeInUtc, ($useCustomComposer ? $composerFile : null));
    }

    private static function getConfigurationDescriptor(MoufManager $moufManager, string $tdbmInstanceName)
    {
        return $moufManager->getInstanceDescriptor($tdbmInstanceName)->getConstructorArgumentProperty('configuration')->getValue();
    }

    private static function getFromConfiguration(MoufManager $moufManager, string $tdbmInstanceName, string $property)
    {
        $configuration = self::getConfigurationDescriptor($moufManager, $tdbmInstanceName);
        if ($configuration === null) {
            throw new \RuntimeException('Unable to find the configuration object linked to TDBMService.');
        }
        return $configuration->getProperty($property)->getValue();
    }

    private static function setInConfiguration(MoufManager $moufManager, string $tdbmInstanceName, string $property, ?string $value)
    {
        $configuration = self::getConfigurationDescriptor($moufManager, $tdbmInstanceName);
        if ($configuration === null) {
            throw new \RuntimeException('Unable to find the configuration object linked to TDBMService.');
        }
        $configuration->getProperty($property)->setValue($value);
    }
}
