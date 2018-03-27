<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Commands;

use TheCodingMachine\TDBM\ConfigurationInterface;
use TheCodingMachine\TDBM\TDBMService;
use Mouf\Utils\Log\Psr\MultiLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{

    /**
     * @var ConfigurationInterface
     */
    private $configuration;

    public function __construct(ConfigurationInterface $configuration)
    {
        parent::__construct();
        $this->configuration = $configuration;
    }

    protected function configure()
    {
        $this->setName('tdbm:generate')
            ->setDescription('Generates DAOs and beans.')
            ->setHelp('Use this command to generate or regenerate the DAOs and beans for your project.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // TODO: externalize composer.json file for autoloading (no more parameters for generateAllDaosAndBeans)

        $alteredConf = new AlteredConfiguration($this->configuration);


        $loggers = [ new ConsoleLogger($output) ];

        $logger = $alteredConf->getLogger();
        if ($logger) {
            $loggers[] = $logger;
        }

        $multiLogger = new MultiLogger($loggers);

        $alteredConf->setLogger($multiLogger);

        $multiLogger->notice('Starting regenerating DAOs and beans');

        $tdbmService = new TDBMService($this->configuration);
        $tdbmService->generateAllDaosAndBeans();

        $multiLogger->notice('Finished regenerating DAOs and beans');
    }
}
