<?php

namespace TheCodingMachine\TDBM\Utils\Annotation;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\Utils\GeneratorListenerInterface;

class AddInterfaceTest extends TestCase
{
    public function testConstruct(): void
    {
        $this->expectException(BadMethodCallException::class);
        new AddInterface(['foo' => 'bar']);
    }

    public function testGetName(): void
    {
        $interfaces = new AddInterface(['name' => GeneratorListenerInterface::class]);
        $this->assertSame(GeneratorListenerInterface::class, $interfaces->getName());
    }
}
