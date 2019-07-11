<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;

class DirectForeignKeyMethodDescriptorTest extends TestCase
{
    public function testGetForeignKey(): void
    {
        $fk = $this->createMock(ForeignKeyConstraint::class);
        $table = $this->createMock(Table::class);
        $ns = $this->createMock(DefaultNamingStrategy::class);
        $ap = $this->createMock(AnnotationParser::class);
        $descriptor = new DirectForeignKeyMethodDescriptor($fk, $table, $ns, $ap);

        $this->assertSame($fk, $descriptor->getForeignKey());
        $this->assertSame($table, $descriptor->getMainTable());
    }
}
