<?php

namespace TheCodingMachine\TDBM\Utils\Annotation;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\Fixtures\Traits\TestUserTrait;
use TheCodingMachine\TDBM\Utils\GeneratorListenerInterface;

class AddTraitTest extends TestCase
{
    public function testConstruct(): void
    {
        $this->expectException(BadMethodCallException::class);
        new AddTrait(['foo' => 'bar']);
    }

    public function testGetName(): void
    {
        $trait = new AddTrait(['name' => TestUserTrait::class]);
        $this->assertSame('\\'.TestUserTrait::class, $trait->getName());
    }

    public function testModifiers(): void
    {
        $trait = new AddTrait(['name' => TestUserTrait::class, 'modifiers' => ['A::foo insteadof B', 'A::bar as baz']]);
        $this->assertSame(['A::foo' => 'B'], $trait->getInsteadOf());
        $this->assertSame(['A::bar' => 'baz'], $trait->getAs());
    }
}
