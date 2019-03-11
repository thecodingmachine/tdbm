<?php

namespace TheCodingMachine\TDBM\Utils;


use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;

class DirectForeignKeyMethodDescriptorTest extends TestCase
{
    public function testGetForeignKey()
    {
        $fk = $this->createMock(ForeignKeyConstraint::class);
        $table = $this->createMock(Table::class);
        $ns = $this->createMock(DefaultNamingStrategy::class);
        $descriptor = new DirectForeignKeyMethodDescriptor($fk, $table, $ns);

        $this->assertSame($fk, $descriptor->getForeignKey());
        $this->assertSame($table, $descriptor->getMainTable());
    }
}
