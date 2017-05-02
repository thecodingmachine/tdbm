<?php

namespace Mouf\Database\TDBM\Commands;


use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\DriverManager;
use Mouf\Database\TDBM\Configuration;
use Mouf\Database\TDBM\Utils\DefaultNamingStrategy;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommandTest extends \PHPUnit_Framework_TestCase
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

        $config = new \Doctrine\DBAL\Configuration();

        $connectionParams = array(
            'user' => $GLOBALS['db_username'],
            'password' => $GLOBALS['db_password'],
            'host' => $GLOBALS['db_host'],
            'port' => $GLOBALS['db_port'],
            'driver' => $GLOBALS['db_driver'],
            'dbname' => $GLOBALS['db_name'],
        );

        $dbConnection = DriverManager::getConnection($connectionParams, $config);
        $configuration = new Configuration('Mouf\\Database\\TDBM\\Test\\Dao\\Bean', 'Mouf\\Database\\TDBM\\Test\\Dao', $dbConnection, new DefaultNamingStrategy(), new ArrayCache(), null, new NullLogger(), []);

        $result = $this->callCommand(new GenerateCommand($configuration), $input);

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
