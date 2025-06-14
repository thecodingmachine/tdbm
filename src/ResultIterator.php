<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Psr\Log\NullLogger;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use Mouf\Database\MagicQuery;
use TheCodingMachine\TDBM\QueryFactory\QueryFactory;
use Psr\Log\LoggerInterface;
use TheCodingMachine\TDBM\Utils\DbalUtils;
use Traversable;

use function array_map;
use function array_pop;
use function is_array;
use function is_int;

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
class ResultIterator implements ResultInterface, \ArrayAccess, \JsonSerializable
{
    /** @var Statement */
    protected $statement;

    /** @var ObjectStorageInterface */
    private $objectStorage;
    /** @var class-string|null */
    private $className;

    /** @var TDBMService */
    private $tdbmService;
    /** @var mixed[] */
    private $parameters;
    /** @var MagicQuery */
    private $magicQuery;

    /** @var QueryFactory */
    private $queryFactory;

    /** @var InnerResultIteratorInterface|null */
    private $innerResultIterator;

    /** @var int|null */
    private $totalCount;

    /** @var int */
    private $mode;

    /** @var LoggerInterface */
    private $logger;

    final private function __construct()
    {
    }

    /**
     * @param mixed[] $parameters
     * @param class-string|null $className
     */
    public static function createResultIterator(QueryFactory $queryFactory, array $parameters, ObjectStorageInterface $objectStorage, ?string $className, TDBMService $tdbmService, MagicQuery $magicQuery, int $mode, LoggerInterface $logger): self
    {
        $iterator =  new static();
        if ($mode !== TDBMService::MODE_CURSOR && $mode !== TDBMService::MODE_ARRAY) {
            throw new TDBMException("Unknown fetch mode: '".$mode."'");
        }

        $iterator->queryFactory = $queryFactory;
        $iterator->objectStorage = $objectStorage;
        $iterator->className = $className;
        $iterator->tdbmService = $tdbmService;
        $iterator->parameters = $parameters;
        $iterator->magicQuery = $magicQuery;
        $iterator->mode = $mode;
        $iterator->logger = $logger;
        return $iterator;
    }

    public static function createEmpyIterator(): self
    {
        $iterator = new static();
        $iterator->totalCount = 0;
        $iterator->logger = new NullLogger();
        return $iterator;
    }

    protected function executeCountQuery(): void
    {
        $sql = $this->magicQuery->buildPreparedStatement($this->queryFactory->getMagicSqlCount(), $this->parameters);
        $this->logger->debug('Running count query: '.$sql);
        $this->totalCount = (int) $this->tdbmService->getConnection()->fetchOne($sql, $this->parameters, DbalUtils::generateTypes($this->parameters));
    }

    /**
     * Counts found records (this is the number of records fetched, taking into account the LIMIT and OFFSET settings).
     *
     * @return int
     */
    public function count(): int
    {
        if ($this->totalCount === null) {
            $this->executeCountQuery();
        }

        return $this->totalCount;
    }

    /**
     * Casts the result set to a PHP array.
     *
     * @return AbstractTDBMObject[]
     */
    public function toArray(): array
    {
        if ($this->totalCount === 0) {
            return [];
        }
        return iterator_to_array($this->getIterator());
    }

    /**
     * Returns a new iterator mapping any call using the $callable function.
     *
     * @param callable $callable
     *
     * @return MapIterator
     */
    public function map(callable $callable): MapIterator
    {
        if ($this->totalCount === 0) {
            return new MapIterator([], $callable);
        }
        return new MapIterator($this->getIterator(), $callable);
    }

    /**
     * Retrieve an external iterator.
     *
     * @return InnerResultIteratorInterface
     */
    public function getIterator(): \Traversable
    {
        if ($this->innerResultIterator === null) {
            if ($this->totalCount === 0) {
                $this->innerResultIterator = new EmptyInnerResultIterator();
            } elseif ($this->mode === TDBMService::MODE_CURSOR) {
                $this->innerResultIterator = InnerResultIterator::createInnerResultIterator($this->queryFactory->getMagicSql(), $this->parameters, null, null, $this->queryFactory->getColumnDescriptors(), $this->objectStorage, $this->className, $this->tdbmService, $this->magicQuery, $this->logger);
            } else {
                $this->innerResultIterator = InnerResultArray::createInnerResultIterator($this->queryFactory->getMagicSql(), $this->parameters, null, null, $this->queryFactory->getColumnDescriptors(), $this->objectStorage, $this->className, $this->tdbmService, $this->magicQuery, $this->logger);
            }
        }

        return $this->innerResultIterator;
    }

    public function take(int $offset, int $limit): PageIterator
    {
        if ($this->totalCount === 0) {
            return PageIterator::createEmpyIterator($this);
        }
        return PageIterator::createResultIterator($this, $this->queryFactory->getMagicSql(), $this->parameters, $limit, $offset, $this->queryFactory->getColumnDescriptors(), $this->objectStorage, $this->className, $this->tdbmService, $this->magicQuery, $this->mode, $this->logger);
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
        return $this->getIterator()->offsetExists($offset);
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
        return $this->getIterator()->offsetGet($offset);
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
        $this->getIterator()->offsetSet($offset, $value);
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
        $this->getIterator()->offsetUnset($offset);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @param bool $stopRecursion Parameter used internally by TDBM to
     *                            stop embedded objects from embedding
     *                            other objects
     *
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *               which is a value of any type other than a resource
     *
     * @since 5.4.0
     */
    public function jsonSerialize($stopRecursion = false): mixed
    {
        return array_map(function (AbstractTDBMObject $item) use ($stopRecursion) {
            return $item->jsonSerialize($stopRecursion);
        }, $this->toArray());
    }

    /**
     * Returns only one value (the first) of the result set.
     * Returns null if no value exists.
     *
     * @return mixed|null
     */
    public function first()
    {
        if ($this->totalCount === 0) {
            return null;
        }
        $page = $this->take(0, 1);
        foreach ($page as $bean) {
            return $bean;
        }

        return null;
    }

    /**
     * Sets the ORDER BY directive executed in SQL and returns a NEW ResultIterator.
     *
     * For instance:
     *
     *  $resultSet = $resultSet->withOrder('label ASC, status DESC');
     *
     * **Important:** TDBM does its best to protect you from SQL injection. In particular, it will only allow column names in the "ORDER BY" clause. This means you are safe to pass input from the user directly in the ORDER BY parameter.
     * If you want to pass an expression to the ORDER BY clause, you will need to tell TDBM to stop checking for SQL injections. You do this by passing a `UncheckedOrderBy` object as a parameter:
     *
     *  $resultSet->withOrder(new UncheckedOrderBy('RAND()'))
     *
     * @param string|UncheckedOrderBy|null $orderBy
     *
     * @return ResultIterator
     */
    public function withOrder($orderBy): ResultIterator
    {
        $clone = clone $this;
        if ($this->totalCount === 0) {
            return $clone;
        }
        $clone->queryFactory = clone $this->queryFactory;
        $clone->queryFactory->sort($orderBy);
        $clone->innerResultIterator = null;

        return $clone;
    }

    /**
     * Sets new parameters for the SQL query and returns a NEW ResultIterator.
     *
     * For instance:
     *
     *  $resultSet = $resultSet->withParameters([ 'status' => 'on' ]);
     *
     * @param mixed[] $parameters
     *
     * @return ResultIterator
     */
    public function withParameters(array $parameters): ResultIterator
    {
        $clone = clone $this;
        if ($this->totalCount === 0) {
            return $clone;
        }
        $clone->parameters = $parameters;
        $clone->innerResultIterator = null;
        $clone->totalCount = null;

        return $clone;
    }

    /**
     * @internal
     * @return string
     */
    public function _getSubQuery(): string
    {
        $this->magicQuery->setOutputDialect(new MySqlPlatform());
        try {
            $sql = $this->magicQuery->build($this->queryFactory->getMagicSqlSubQuery(), $this->parameters);
        } finally {
            $this->magicQuery->setOutputDialect($this->tdbmService->getConnection()->getDatabasePlatform());
        }
        $primaryKeyColumnDescs = $this->queryFactory->getSubQueryColumnDescriptors();

        if (count($primaryKeyColumnDescs) > 1) {
            throw new TDBMException('You cannot use in a sub-query a table that has a primary key on more that 1 column.');
        }

        $pkDesc = array_pop($primaryKeyColumnDescs);

        $mysqlPlatform = new MySqlPlatform();
        $sql = $mysqlPlatform->quoteIdentifier($pkDesc['table']).'.'.$mysqlPlatform->quoteIdentifier($pkDesc['column']).' IN ('.$sql.')';

        return $sql;
    }
}
