<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Commands;

use TheCodingMachine\TDBM\Configuration;
use TheCodingMachine\TDBM\TDBMAbstractServiceTest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use function file_exists;
use function unlink;

class GenerateCommandTest extends TDBMAbstractServiceTest
{
    public function testCall(): void
    {
        $input = new ArrayInput([], new InputDefinition([]));
        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        //let's delete the lock file
        $schemaFilePath = Configuration::getDefaultLockFilePath();
        if (file_exists($schemaFilePath)) {
            unlink($schemaFilePath);
        }
        (new GenerateCommand($this->getConfiguration()))->run($input, $output);
        $result = $output->fetch();

        $this->assertContains('Finished regenerating DAOs and beans', $result);
        //Check that the lock file was generated
        $this->assertFileExists($schemaFilePath);
    }
}
