<?php

namespace TheCodingMachine\TDBM\Utils\Logs;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class LevelFilterTest extends TestCase
{
    public function testIsPsrLog()
    {
        $levelFilter = new LevelFilter($this->getMockLoggerInterface(), LogLevel::DEBUG);
        $this->assertInstanceOf('\Psr\Log\LoggerInterface', $levelFilter);
    }

    public function testLog()
    {
        $loggerInterface = $this->getMockLoggerInterface();

        // We expect this test to call the loggerInterface twice to log the ERROR and CRITICAL messages
        // and to ignore the WARNING log message
        $loggerInterface->expects($this->exactly(2))
            ->method('log')
            ->withConsecutive(
                [LogLevel::ERROR],
                [LogLevel::CRITICAL]
            );

        $levelFilter = new LevelFilter($loggerInterface, LogLevel::ERROR);

        // Log message with equal priority to the level filter (should be get logged)
        $levelFilter->log(LogLevel::ERROR, 'TEST ERROR MESSAGE');

        // Log message with higher priority than the level filter (should be logged)
        $levelFilter->log(LogLevel::CRITICAL, 'TEST CRITICAL MESSAGE');

        // Log message with lower priority than the level filter (should not be logged)
        $levelFilter->log(LogLevel::WARNING, 'TEST WARNING MESSAGE');
    }

    public function testInvalidConstructorLogLevel()
    {
        // We expect an exception to be thrown when specifying an invalid logging level in the constructor
        $this->expectException('\Psr\Log\InvalidArgumentException');
        $levelFilter = new LevelFilter($this->getMockLoggerInterface(), 'InvalidLogLevel');
    }

    public function testInvalidLogLogLevel()
    {
        // We expect an exception to be thrown when specifying an invalid logging level in the log method parameter
        $levelFilter = new LevelFilter($this->getMockLoggerInterface(), LogLevel::DEBUG);

        $this->expectException('\Psr\Log\InvalidArgumentException');
        $levelFilter->log('InvalidLogLevel', 'TEST LOG MESSAGE');
    }

    /**
     * @return LoggerInterface
     */
    protected function getMockLoggerInterface()
    {
        $loggerInterface = $this->createMock('\Psr\Log\LoggerInterface');
        return $loggerInterface;
    }
}
