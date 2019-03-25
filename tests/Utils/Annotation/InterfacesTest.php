<?php

namespace TheCodingMachine\TDBM\Utils\Annotation;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\Utils\GeneratorListenerInterface;

class InterfacesTest extends TestCase
{

    public function testConstruct(): void
    {
        $this->expectException(BadMethodCallException::class);
        new Interfaces(['foo' => 'bar']);
    }

    public function testGetNames(): void
    {
        $interfaces = new Interfaces(['names' => [GeneratorListenerInterface::class]]);
        $this->assertSame([GeneratorListenerInterface::class], $interfaces->getNames());
    }
}
