<?php
namespace Mouf\Database\TDBM\Commands;


use Mouf\Database\TDBM\TDBMService;
use Symfony\Component\Console\Command\Command;

class GenerateCommand extends Command
{

    /**
     * @var TDBMService
     */
    private $tdbmService;

    public function __construct(TDBMService $tdbmService)
    {
        $this->tdbmService = $tdbmService;
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
        // TODO: wrap MultiLogger in configuration.
        // TODO: externalize composer.json file for autoloading (no more parameters for generateAllDaosAndBeans)

        $this->tdbmService->generateAllDaosAndBeans();
    }
}
