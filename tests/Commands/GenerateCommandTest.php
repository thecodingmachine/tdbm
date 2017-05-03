<?php

namespace TheCodingMachine\TDBM\Commands;

use TheCodingMachine\TDBM\TDBMAbstractServiceTest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommandTest extends TDBMAbstractServiceTest
{
    public static function getInputDefinition()
    {
        return new InputDefinition([
        ]);
    }

    public function testCall()
    {
        $input = new ArrayInput([
        ], self::getInputDefinition());

        $result = $this->callCommand(new GenerateCommand($this->getConfiguration()), $input);

        $this->assertContains('Finished regenerating DAOs and beans', $result);
    }

    /**
     * Calls the command passed in parameter. Returns the output.
     *
     * @param Command $command
     * @param InputInterface $input
     * @return string
     */
    protected function callCommand(Command $command, InputInterface $input) : string
    {
        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        $r = new \ReflectionMethod($command, 'execute');
        $r->setAccessible(true);
        $r->invoke($command, $input, $output);

        return $output->fetch();
    }
}
