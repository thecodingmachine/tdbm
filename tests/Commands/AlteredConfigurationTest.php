<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Commands;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\Configuration;
use TheCodingMachine\TDBM\Utils\NamingStrategyInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AlteredConfigurationTest extends TestCase
{
    public function testAlteredConfiguration(): void
    {
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $namingStrategy = $this->getMockBuilder(NamingStrategyInterface::class)->disableOriginalConstructor()->getMock();
        $cache = $this->getMockBuilder(Cache::class)->disableOriginalConstructor()->getMock();
        $logger = $this->getMockBuilder(LoggerInterface::class)->disableOriginalConstructor()->getMock();
        $schemaAnalyzer = $this->getMockBuilder(SchemaAnalyzer::class)->disableOriginalConstructor()->getMock();

        $configuration = new Configuration('FooBean', 'FooDao', $connection, $namingStrategy, $cache, $schemaAnalyzer, $logger, [], null, [], 'FooResultIterator');

        $alteredConfiguration = new AlteredConfiguration($configuration);

        $this->assertSame($connection, $alteredConfiguration->getConnection());
        $this->assertSame($namingStrategy, $alteredConfiguration->getNamingStrategy());
        $this->assertSame($cache, $alteredConfiguration->getCache());
        $this->assertSame($logger, $alteredConfiguration->getLogger());
        $this->assertSame('FooBean', $alteredConfiguration->getBeanNamespace());
        $this->assertSame('FooDao', $alteredConfiguration->getDaoNamespace());
        $this->assertSame('FooResultIterator', $alteredConfiguration->getResultIteratorNamespace());
        $this->assertSame($configuration->getGeneratorEventDispatcher(), $alteredConfiguration->getGeneratorEventDispatcher());
        $this->assertSame($schemaAnalyzer, $alteredConfiguration->getSchemaAnalyzer());
        $this->assertSame($configuration->getPathFinder(), $alteredConfiguration->getPathFinder());
        $this->assertSame($configuration->getAnnotationParser(), $alteredConfiguration->getAnnotationParser());
        $this->assertSame($configuration->getLockFilePath(), $alteredConfiguration->getLockFilePath());
        $this->assertSame($configuration->getCodeGeneratorListener(), $alteredConfiguration->getCodeGeneratorListener());

        $logger2 = new NullLogger();
        $alteredConfiguration->setLogger($logger2);
        $this->assertSame($logger2, $alteredConfiguration->getLogger());
    }
}
