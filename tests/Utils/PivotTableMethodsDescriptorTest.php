<?php

namespace TheCodingMachine\TDBM\Utils;


use PHPUnit\Framework\TestCase;

class PivotTableMethodsDescriptorTest extends TestCase
{

    public function testGetters()
    {
        $table = $this->createMock(Table::class);
        $localFk = $this->createMock(ForeignKeyConstraint::class);
        $remoteFk = $this->createMock(ForeignKeyConstraint::class);
        $ns = $this->createMock(DefaultNamingStrategy::class);
        $descriptor = new PivotTableMethodsDescriptor($table, $fk1, $fk2, $ns, 'Bean\Namespace');

        $this->assertSame($table, $descriptor->getPivotTable());
        $this->assertSame($localFk, $descriptor->getLocalFk());
        $this->assertSame($remoteFk, $descriptor->getRemoteFk());
    }
}
