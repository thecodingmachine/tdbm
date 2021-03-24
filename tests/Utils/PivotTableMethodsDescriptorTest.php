<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;

class PivotTableMethodsDescriptorTest extends TestCase
{
    public function testGetters(): void
    {
        $table = $this->createMock(Table::class);
        $localFk = new ForeignKeyConstraint(['foo'], new Table('table1'), ['lol']);
        $localFk->setLocalTable(new Table('table3'));
        $remoteFk = new ForeignKeyConstraint(['foo2'], new Table('table2'), ['lol2']);
        $remoteFk->setLocalTable(new Table('table3'));
        $ns = $this->createMock(DefaultNamingStrategy::class);
        $descriptor = new PivotTableMethodsDescriptor($table, $localFk, $remoteFk, $ns, AnnotationParser::buildWithDefaultAnnotations([]), 'Bean\Namespace', 'ResultIterator\Namespace');

        $this->assertSame($table, $descriptor->getPivotTable());
        $this->assertSame($localFk, $descriptor->getLocalFk());
        $this->assertSame($remoteFk, $descriptor->getRemoteFk());
    }
}
