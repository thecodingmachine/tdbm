<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use TheCodingMachine\TDBM\ConfigurationInterface;
use TheCodingMachine\TDBM\TDBMSchemaAnalyzer;

class BeanRegistry
{
    /** @var BeanDescriptor[] table_name => BeanDescriptor */
    private $registry = [];
    /** @var ConfigurationInterface */
    private $configuration;
    /** @var Schema */
    private $schema;
    /** @var TDBMSchemaAnalyzer */
    private $tdbmSchemaAnalyzer;
    /** @var NamingStrategyInterface */
    private $namingStrategy;

    /**
     * BeanRegistry constructor.
     * @param ConfigurationInterface $configuration
     * @param Schema $schema
     * @param TDBMSchemaAnalyzer $tdbmSchemaAnalyzer
     * @param NamingStrategyInterface $namingStrategy
     */
    public function __construct(
        ConfigurationInterface $configuration,
        Schema $schema,
        TDBMSchemaAnalyzer $tdbmSchemaAnalyzer,
        NamingStrategyInterface $namingStrategy
    ) {
        $this->configuration = $configuration;
        $this->schema = $schema;
        $this->tdbmSchemaAnalyzer = $tdbmSchemaAnalyzer;
        $this->namingStrategy = $namingStrategy;
    }

    public function hasBeanForTable(Table $table): bool
    {
        return isset($this->registry[$table->getName()]);
    }

    public function addBeanForTable(Table $table): BeanDescriptor
    {
        if (!$this->hasBeanForTable($table)) {
            $this->registry[$table->getName()] = new BeanDescriptor(
                $table,
                $this->configuration->getBeanNamespace(),
                $this->configuration->getBeanNamespace() . '\\Generated',
                $this->configuration->getDaoNamespace(),
                $this->configuration->getDaoNamespace() . '\\Generated',
                $this->configuration->getResultIteratorNamespace(),
                $this->configuration->getResultIteratorNamespace() . '\\Generated',
                $this->configuration->getSchemaAnalyzer(),
                $this->schema,
                $this->tdbmSchemaAnalyzer,
                $this->namingStrategy,
                $this->configuration->getAnnotationParser(),
                $this->configuration->getCodeGeneratorListener(),
                $this->configuration,
                $this
            );
        }
        return $this->getBeanForTableName($table->getName());
    }

    public function getBeanForTableName(string $table): BeanDescriptor
    {
        return $this->registry[$table];
    }
}
