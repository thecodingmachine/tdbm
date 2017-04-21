<?php


namespace Mouf\Database\TDBM\Utils;

use Mouf\Database\TDBM\ConfigurationInterface;
use Mouf\Database\TDBM\MoufConfiguration;
use Mouf\Database\TDBM\TDBMService;
use Mouf\MoufManager;

class MoufDiListener implements GeneratorListenerInterface
{

    /**
     * @param ConfigurationInterface $configuration
     * @param BeanDescriptorInterface[] $beanDescriptors
     */
    public function onGenerate(ConfigurationInterface $configuration, array $beanDescriptors): void
    {
        // Let's generate the needed instance in Mouf.
        $moufManager = MoufManager::getMoufManager();

        $daoFactoryInstanceName = null;
        if ($configuration instanceof MoufConfiguration) {
            $daoFactoryInstanceName = $configuration->getDaoFactoryInstanceName();
            $daoFactoryClassName = $configuration->getDaoNamespace().'\\Generated\\'.$configuration->getNamingStrategy()->getDaoFactoryClassName();
            $moufManager->declareComponent($daoFactoryInstanceName, $daoFactoryClassName, false, MoufManager::DECLARE_ON_EXIST_KEEP_INCOMING_LINKS);
        }

        $tdbmServiceInstanceName = $this->getTdbmInstanceName($configuration);

        foreach ($beanDescriptors as $beanDescriptor) {
            $daoName = $beanDescriptor->getDaoClassName();

            $instanceName = TDBMDaoGenerator::toVariableName($daoName);
            if (!$moufManager->instanceExists($instanceName)) {
                $moufManager->declareComponent($instanceName, $configuration->getDaoNamespace().'\\'.$daoName);
            }
            $moufManager->setParameterViaConstructor($instanceName, 0, $tdbmServiceInstanceName, 'object');
            if ($daoFactoryInstanceName !== null) {
                $moufManager->bindComponentViaSetter($daoFactoryInstanceName, 'set'.$daoName, $instanceName);
            }
        }

        $moufManager->rewriteMouf();
    }

    private function getTdbmInstanceName(ConfigurationInterface $configuration) : string
    {
        $moufManager = MoufManager::getMoufManager();

        $configurationInstanceName = $moufManager->findInstanceName($configuration);
        if (!$configurationInstanceName) {
            throw new \TDBMException('Could not find TDBM instance for configuration object.');
        }

        // Let's find the configuration
        $tdbmServicesNames = $moufManager->findInstances(TDBMService::class);

        foreach ($tdbmServicesNames as $name) {
            if ($moufManager->getInstanceDescriptor($name)->getConstructorArgumentProperty('configuration')->getValue()->getName() === $configurationInstanceName) {
                return $name;
            }
        }

        throw new \TDBMException('Could not find TDBMService instance.');
    }
}
