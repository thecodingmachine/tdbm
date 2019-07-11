<?php

namespace TheCodingMachine\TDBM\Utils\Annotation;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\Utils\GeneratorListenerInterface;

class AddInterfaceOnDaoTest extends TestCase
{
    public function testConstruct(): void
    {
        $this->expectException(BadMethodCallException::class);
        new AddInterfaceOnDao(['foo' => 'bar']);
    }

    public function testGetName(): void
    {
        $interfaces = new AddInterfaceOnDao(['name' => GeneratorListenerInterface::class]);
        $this->assertSame(GeneratorListenerInterface::class, $interfaces->getName());
    }
}
