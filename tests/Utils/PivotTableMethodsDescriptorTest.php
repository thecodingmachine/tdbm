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
        $localFk = $this->createMock(ForeignKeyConstraint::class);
        $remoteFk = $this->createMock(ForeignKeyConstraint::class);
        $ns = $this->createMock(DefaultNamingStrategy::class);
        $descriptor = new PivotTableMethodsDescriptor($table, $localFk, $remoteFk, $ns, 'Bean\Namespace', AnnotationParser::buildWithDefaultAnnotations([]));

        $this->assertSame($table, $descriptor->getPivotTable());
        $this->assertSame($localFk, $descriptor->getLocalFk());
        $this->assertSame($remoteFk, $descriptor->getRemoteFk());
    }
}
