<?php

namespace Mouf\Database\TDBM;

use Doctrine\DBAL\Statement;
use Mouf\Database\MagicQuery;
use Porpaginas\Result;
use Traversable;

/*
 Copyright (C) 2006-2016 David Négrier - THE CODING MACHINE

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
class ResultIterator implements Result, \ArrayAccess, \JsonSerializable
{
    /**
     * @var Statement
     */
    protected $statement;

    protected $fetchStarted = false;
    private $objectStorage;
    private $className;

    private $tdbmService;
    private $magicSql;
    private $magicSqlCount;
    private $parameters;
    private $columnDescriptors;
    private $magicQuery;

    /**
     * @var InnerResultIterator
     */
    private $innerResultIterator;

    /**
     * The key of the current retrieved object.
     *
     * @var int
     */
    protected $key = -1;

    protected $current = null;

    private $databasePlatform;

    private $totalCount;

    private $mode;

    public function __construct($magicSql, $magicSqlCount, array $parameters, array $columnDescriptors, $objectStorage, $className, TDBMService $tdbmService, MagicQuery $magicQuery, $mode)
    {
        $this->magicSql = $magicSql;
        $this->magicSqlCount = $magicSqlCount;
        $this->objectStorage = $objectStorage;
        $this->className = $className;
        $this->tdbmService = $tdbmService;
        $this->parameters = $parameters;
        $this->columnDescriptors = $columnDescriptors;
        $this->magicQuery = $magicQuery;
        $this->databasePlatform = $this->tdbmService->getConnection()->getDatabasePlatform();
        $this->mode = $mode;
    }

    protected function executeCountQuery()
    {
        $sql = $this->magicQuery->build($this->magicSqlCount, $this->parameters);
        $this->totalCount = $this->tdbmService->getConnection()->fetchColumn($sql, $this->parameters);
    }

    /**
     * Counts found records (this is the number of records fetched, taking into account the LIMIT and OFFSET settings).
     *
     * @return int
     */
    public function count()
    {
        if ($this->totalCount === null) {
            $this->executeCountQuery();
        }

        return $this->totalCount;
    }

    /**
     * Casts the result set to a PHP array.
     *
     * @return array
     */
    public function toArray()
    {
        return iterator_to_array($this->getIterator());
    }

    /**
     * Returns a new iterator mapping any call using the $callable function.
     *
     * @param callable $callable
     *
     * @return MapIterator
     */
    public function map(callable $callable)
    {
        return new MapIterator($this->getIterator(), $callable);
    }

    /**
     * Retrieve an external iterator.
     *
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     *
     * @return InnerResultIterator An instance of an object implementing <b>Iterator</b> or
     *                             <b>Traversable</b>
     *
     * @since 5.0.0
     */
    public function getIterator()
    {
        if ($this->innerResultIterator === null) {
            if ($this->mode === TDBMService::MODE_CURSOR) {
                $this->innerResultIterator = new InnerResultIterator($this->magicSql, $this->parameters, null, null, $this->columnDescriptors, $this->objectStorage, $this->className, $this->tdbmService, $this->magicQuery);
            } else {
                $this->innerResultIterator = new InnerResultArray($this->magicSql, $this->parameters, null, null, $this->columnDescriptors, $this->objectStorage, $this->className, $this->tdbmService, $this->magicQuery);
            }
        }

        return $this->innerResultIterator;
    }

    /**
     * @param int $offset
     *
     * @return PageIterator
     */
    public function take($offset, $limit)
    {
        return new PageIterator($this, $this->magicSql, $this->parameters, $limit, $offset, $this->columnDescriptors, $this->objectStorage, $this->className, $this->tdbmService, $this->magicQuery, $this->mode);
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
     *              The return value will be casted to boolean if non-boolean was returned.
     *
     * @since 5.0.0
     */
    public function offsetExists($offset)
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
     * @return mixed Can return all value types.
     *
     * @since 5.0.0
     */
    public function offsetGet($offset)
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
    public function offsetSet($offset, $value)
    {
        return $this->getIterator()->offsetSet($offset, $value);
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
    public function offsetUnset($offset)
    {
        return $this->getIterator()->offsetUnset($offset);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @param bool $stopRecursion Parameter used internally by TDBM to
     *                            stop embedded objects from embedding
     *                            other objects.
     *
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *               which is a value of any type other than a resource.
     *
     * @since 5.4.0
     */
    public function jsonSerialize($stopRecursion = false)
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
        $page = $this->take(0, 1);
        foreach ($page as $bean) {
            return $bean;
        }

        return;
    }
}
