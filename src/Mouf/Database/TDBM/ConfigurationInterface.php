<?php

namespace Mouf\Database\TDBM;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use Mouf\Database\TDBM\Utils\GeneratorListenerInterface;
use Mouf\Database\TDBM\Utils\NamingStrategyInterface;
use Mouf\Database\TDBM\Utils\PathFinder\PathFinder;
use Mouf\Database\TDBM\Utils\PathFinder\PathFinderInterface;
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
     * Returns a class able to find the place of a PHP file based on the class name.
     * Useful to find the path where DAOs and beans should be written to.
     *
     * @return PathFinderInterface
     */
    public function getPathFinder() : PathFinderInterface;
}
