<?php


namespace TheCodingMachine\TDBM\Utils\Logs;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use function array_search;
use function sprintf;

class LevelFilter extends AbstractLogger
{
    /**
     * Logging levels from syslog protocol defined in RFC 5424
     *
     * @var string[] $levels Logging levels
     */
    private static $levels = array(
        LogLevel::EMERGENCY, // 0
        LogLevel::ALERT,     // 1
        LogLevel::CRITICAL,  // 2
        LogLevel::ERROR,     // 3
        LogLevel::WARNING,   // 4
        LogLevel::NOTICE,    // 5
        LogLevel::INFO,      // 6
        LogLevel::DEBUG      // 7
    );

    /**
     * @var int
     */
    private $logLevel;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     * @param string $level \Psr\Log\LogLevel string
     */
    public function __construct(LoggerInterface $logger, string $level)
    {
        $this->logger = $logger;

        $this->logLevel = array_search($level, self::$levels, true);
        if ($this->logLevel === false) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot use logging level "%s"',
                    $level
                )
            );
        }
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        $levelCode = array_search($level, self::$levels, true);
        if ($levelCode === false) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot use unknown logging level "%s"',
                    $level
                )
            );
        }
        if ($levelCode > $this->logLevel) {
            return;
        }

        $this->logger->log($level, $message, $context);
    }
}
