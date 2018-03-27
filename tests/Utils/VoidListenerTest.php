<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use TheCodingMachine\TDBM\ConfigurationInterface;

class VoidListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testNothing()
    {
        $configuration = $this->getMockBuilder(ConfigurationInterface::class)->getMock();
        $voidListener = new VoidListener();
        $voidListener->onGenerate($configuration, []);

        // Hum... no way to test nothing happened!
    }
}
