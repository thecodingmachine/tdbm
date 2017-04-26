<?php

namespace Mouf\Database\TDBM;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use Mouf\Database\TDBM\Utils\GeneratorListenerInterface;
use Mouf\Database\TDBM\Utils\NamingStrategyInterface;
use Psr\Log\LoggerInterface;

interface ConfigurationInterface
{
    /**
     * @return string
     */
    public function getBeanNamespace(): string;

    /**
     * @return string
     */
    public function getDaoNamespace(): string;

    /**
     * @return Connection
     */
    public function getConnection(): Connection;

    /**
     * @return Cache
     */
    public function getCache(): Cache;

    /**
     * @return NamingStrategyInterface
     */
    public function getNamingStrategy(): NamingStrategyInterface;

    /**
     * @return SchemaAnalyzer
     */
    public function getSchemaAnalyzer(): SchemaAnalyzer;

    /**
     * @return LoggerInterface
     */
    public function getLogger(): ?LoggerInterface;

    /**
     * @return GeneratorListenerInterface
     */
    public function getGeneratorEventDispatcher(): GeneratorListenerInterface;

    /**
     * Get the Composer file used to detect the path where files should be written.
     * Path is relative to the root directory (this function will typically return 'composer.json' unless you want to write the beans and DAOs in a package).
     *
     * @return null|string
     */
    public function getComposerFile() : string;
}
