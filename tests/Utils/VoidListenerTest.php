<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\ConfigurationInterface;

class VoidListenerTest extends TestCase
{
    public function testNothing(): void
    {
        $configuration = $this->getMockBuilder(ConfigurationInterface::class)->getMock();
        $voidListener = new VoidListener();
        $voidListener->onGenerate($configuration, []);

        // Hum... no way to test nothing happened! :)
        $this->assertTrue(true);
    }
}
