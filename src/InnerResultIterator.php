<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Result;
use Mouf\Database\MagicQuery;
use Psr\Log\LoggerInterface;
use TheCodingMachine\TDBM\Utils\DbalUtils;

/*
 Copyright (C) 2006-2017 David Négrier - THE CODING MACHINE

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Iterator used to retrieve results.
 */
class InnerResultIterator implements \Iterator, InnerResultIteratorInterface
{
    protected ?Result $result = null;

    /** @var bool */
    protected $fetchStarted = false;
    /** @var ObjectStorageInterface */
    private $objectStorage;
    /** @var string|null */
    private $className;

    /** @var TDBMService */
    private $tdbmService;
    /** @var string */
    private $magicSql;
    /** @var mixed[] */
    private $parameters;
    /** @var int|null */
    private $limit;
    /** @var int|null */
    private $offset;
    /** @var array[] */
    private $columnDescriptors;
    /** @var MagicQuery */
    private $magicQuery;

    /**
     * The key of the current retrieved object.
     *
     * @var int
     */
    protected $key = -1;

    /** @var AbstractTDBMObject|null */
    protected $current = null;

    /** @var AbstractPlatform */
    private $databasePlatform;

    /** @var LoggerInterface */
    private $logger;

    /** @var int|null */
    protected $count = null;

    final private function __construct()
    {
    }

    /**
     * @param mixed[] $parameters
     * @param array[] $columnDescriptors
     */
    public static function createInnerResultIterator(string $magicSql, array $parameters, ?int $limit, ?int $offset, array $columnDescriptors, ObjectStorageInterface $objectStorage, ?string $className, TDBMService $tdbmService, MagicQuery $magicQuery, LoggerInterface $logger): self
    {
        $iterator = new static();
        $iterator->magicSql = $magicSql;
        $iterator->objectStorage = $objectStorage;
        $iterator->className = $className;
        $iterator->tdbmService = $tdbmService;
        $iterator->parameters = $parameters;
        $iterator->limit = $limit;
        $iterator->offset = $offset;
        $iterator->columnDescriptors = $columnDescriptors;
        $iterator->magicQuery = $magicQuery;
        $iterator->databasePlatform = $iterator->tdbmService->getConnection()->getDatabasePlatform();
        $iterator->logger = $logger;
        return $iterator;
    }

    private function getQuery(): string
    {
        $sql = $this->magicQuery->buildPreparedStatement($this->magicSql, $this->parameters);
        $sql = $this->tdbmService->getConnection()->getDatabasePlatform()->modifyLimitQuery($sql, $this->limit, $this->offset);
        return $sql;
    }

    protected function executeQuery(): void
    {
        $sql = $this->getQuery();

        $this->logger->debug('Running SQL request: '.$sql);

        $this->result = $this->tdbmService->getConnection()->executeQuery($sql, $this->parameters, DbalUtils::generateTypes($this->parameters));

        $this->fetchStarted = true;
    }

    /**
     * Counts found records (this is the number of records fetched, taking into account the LIMIT and OFFSET settings).
     *
     * @return int
     */
    public function count(): int
    {
        if ($this->count !== null) {
            return $this->count;
        }

        if ($this->fetchStarted && $this->tdbmService->getConnection()->getDatabasePlatform() instanceof MySqlPlatform) {
            // Optimisation: we don't need a separate "count" SQL request in MySQL.
            assert($this->result instanceof Result);
            $this->count = (int)$this->result->rowCount();
            return $this->count;
        }
        return $this->getRowCountViaSqlQuery();
    }

    /**
     * Makes a separate SQL query to compute the row count.
     * (not needed in MySQL if fetch is already done)
     */
    private function getRowCountViaSqlQuery(): int
    {
        $countSql = 'SELECT COUNT(1) FROM ('.$this->getQuery().') c';

        $this->logger->debug('Running count SQL request: '.$countSql);

        $this->count = (int) $this->tdbmService->getConnection()->fetchOne($countSql, $this->parameters, DbalUtils::generateTypes($this->parameters));
        return $this->count;
    }

    /**
     * Fetches record at current cursor.
     *
     * @return AbstractTDBMObject
     */
    public function current(): mixed
    {
        return $this->current;
    }

    /**
     * Returns the current result's key.
     *
     * @return int
     */
    public function key(): mixed
    {
        return $this->key;
    }

    /**
     * Advances the cursor to the next result.
     * Casts the database result into one (or several) beans.
     */
    public function next(): void
    {
        $row = $this->result->fetchAssociative();
        if ($row) {
            /** @var array<string, array<string, array<string, mixed>>> $beansData array<tablegroup, array<table, array<column, value>>>*/
            $beansData = [];
            $allNull = true;
            foreach ($row as $key => $value) {
                if (!isset($this->columnDescriptors[$key])) {
                    continue;
                }
                if ($allNull !== false && $value !== null) {
                    $allNull = false;
                }

                $columnDescriptor = $this->columnDescriptors[$key];

                if ($columnDescriptor['tableGroup'] === null) {
                    // A column can have no tableGroup (if it comes from an ORDER BY expression)
                    continue;
                }

                // Let's cast the value according to its type
                $value = $columnDescriptor['type']->convertToPHPValue($value, $this->databasePlatform);

                $beansData[$columnDescriptor['tableGroup']][$columnDescriptor['table']][$columnDescriptor['column']] = $value;
            }
            if ($allNull === true) {
                $this->next();
                return;
            }

            $reflectionClassCache = [];
            $firstBean = true;
            /** @var array<string, array<string, mixed>> $beanData */
            foreach ($beansData as $beanData) {
                // Let's find the bean class name associated to the bean.

                list($actualClassName, $mainBeanTableName, $tablesUsed) = $this->tdbmService->_getClassNameFromBeanData($beanData);

                // @TODO (gua) this is a weird hack to be able to force a TDBMObject...
                // `$this->className` could be used to override `$actualClassName`
                if ($this->className !== null && is_a($this->className, TDBMObject::class, true)) {
                    $actualClassName = $this->className;
                }

                // Let's filter out the beanData that is not used (because it belongs to a part of the hierarchy that is not fetched:
                foreach ($beanData as $tableName => $descriptors) {
                    if (!in_array($tableName, $tablesUsed)) {
                        unset($beanData[$tableName]);
                    }
                }

                // Must we create the bean? Let's see in the cache if we have a mapping DbRow?
                // Let's get the first object mapping a row:
                // We do this loop only for the first table

                $primaryKeys = $this->tdbmService->_getPrimaryKeysFromObjectData($mainBeanTableName, $beanData[$mainBeanTableName]);
                $hash = $this->tdbmService->getObjectHash($primaryKeys);

                $dbRow = $this->objectStorage->get($mainBeanTableName, $hash);
                if ($dbRow !== null) {
                    $bean = $dbRow->getTDBMObject();
                } else {
                    // Let's construct the bean
                    if (!isset($reflectionClassCache[$actualClassName])) {
                        $reflectionClassCache[$actualClassName] = new \ReflectionClass($actualClassName);
                    }
                    // Let's bypass the constructor when creating the bean!
                    /** @var AbstractTDBMObject $bean */
                    $bean = $reflectionClassCache[$actualClassName]->newInstanceWithoutConstructor();
                    $bean->_constructFromData($beanData, $this->tdbmService);
                }

                // The first bean is the one containing the main table.
                if ($firstBean) {
                    $firstBean = false;
                    $this->current = $bean;
                }
            }

            ++$this->key;
        } else {
            $this->current = null;
        }
    }

    /**
     * Moves the cursor to the beginning of the result set.
     */
    public function rewind(): void
    {
        $this->executeQuery();
        $this->key = -1;
        $this->next();
    }
    /**
     * Checks if the cursor is reading a valid result.
     *
     * @return bool
     */
    public function valid(): bool
    {
        return $this->current !== null;
    }

    /**
     * Whether a offset exists.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset <p>
     *                      An offset to check for.
     *                      </p>
     *
     * @return bool true on success or false on failure.
     *              </p>
     *              <p>
     *              The return value will be casted to boolean if non-boolean was returned
     *
     * @since 5.0.0
     */
    public function offsetExists($offset): bool
    {
        throw new TDBMInvalidOperationException('You cannot access this result set via index because it was fetched in CURSOR mode. Use ARRAY_MODE instead.');
    }

    /**
     * Offset to retrieve.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset <p>
     *                      The offset to retrieve.
     *                      </p>
     *
     * @return mixed Can return all value types
     *
     * @since 5.0.0
     */
    public function offsetGet($offset): mixed
    {
        throw new TDBMInvalidOperationException('You cannot access this result set via index because it was fetched in CURSOR mode. Use ARRAY_MODE instead.');
    }

    /**
     * Offset to set.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     *                      The offset to assign the value to.
     *                      </p>
     * @param mixed $value  <p>
     *                      The value to set.
     *                      </p>
     *
     * @since 5.0.0
     */
    public function offsetSet($offset, $value): void
    {
        throw new TDBMInvalidOperationException('You cannot set values in a TDBM result set.');
    }

    /**
     * Offset to unset.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset <p>
     *                      The offset to unset.
     *                      </p>
     *
     * @since 5.0.0
     */
    public function offsetUnset($offset): void
    {
        throw new TDBMInvalidOperationException('You cannot unset values in a TDBM result set.');
    }
}
