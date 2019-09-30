<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM;

use Doctrine\DBAL\Statement;
use Mouf\Database\MagicQuery;
use Porpaginas\Page;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
class PageIterator implements Page, \ArrayAccess, \JsonSerializable
{
    /**
     * @var Statement
     */
    protected $statement;

    protected $fetchStarted = false;
    private $objectStorage;
    private $className;

    private $parentResult;
    private $tdbmService;
    private $magicSql;
    private $parameters;
    private $limit;
    private $offset;
    private $columnDescriptors;
    private $magicQuery;

    /**
     * The key of the current retrieved object.
     *
     * @var int
     */
    protected $key = -1;

    protected $current;

    private $innerResultIterator;

    private $mode;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private function __construct()
    {
    }

    /**
     * @param mixed[] $parameters
     * @param array[] $columnDescriptors
     */
    public static function createResultIterator(ResultIterator $parentResult, string $magicSql, array $parameters, int $limit, int $offset, array $columnDescriptors, ObjectStorageInterface $objectStorage, ?string $className, TDBMService $tdbmService, MagicQuery $magicQuery, int $mode, LoggerInterface $logger): self
    {
        $iterator =  new self();
        $iterator->parentResult = $parentResult;
        $iterator->magicSql = $magicSql;
        $iterator->objectStorage = $objectStorage;
        $iterator->className = $className;
        $iterator->tdbmService = $tdbmService;
        $iterator->parameters = $parameters;
        $iterator->limit = $limit;
        $iterator->offset = $offset;
        $iterator->columnDescriptors = $columnDescriptors;
        $iterator->magicQuery = $magicQuery;
        $iterator->mode = $mode;
        $iterator->logger = $logger;
        return $iterator;
    }

    public static function createEmpyIterator(ResultIterator $parentResult): self
    {
        $iterator = new self();
        $iterator->parentResult = $parentResult;
        $iterator->logger = new NullLogger();
        return $iterator;
    }

    /**
     * Retrieve an external iterator.
     *
     * @return InnerResultIteratorInterface
     */
    public function getIterator()
    {
        if ($this->innerResultIterator === null) {
            if ($this->parentResult->count() === 0) {
                $this->innerResultIterator = new EmptyInnerResultIterator();
            } elseif ($this->mode === TDBMService::MODE_CURSOR) {
                $this->innerResultIterator = InnerResultIterator::createInnerResultIterator($this->magicSql, $this->parameters, $this->limit, $this->offset, $this->columnDescriptors, $this->objectStorage, $this->className, $this->tdbmService, $this->magicQuery, $this->logger);
            } else {
                $this->innerResultIterator = InnerResultArray::createInnerResultIterator($this->magicSql, $this->parameters, $this->limit, $this->offset, $this->columnDescriptors, $this->objectStorage, $this->className, $this->tdbmService, $this->magicQuery, $this->logger);
            }
        }

        return $this->innerResultIterator;
    }

    /**
     * @return int
     */
    public function getCurrentOffset()
    {
        return $this->offset;
    }

    /**
     * @return int
     */
    public function getCurrentPage()
    {
        return (int) floor($this->offset / $this->limit) + 1;
    }

    /**
     * @return int
     */
    public function getCurrentLimit()
    {
        return $this->limit;
    }

    /**
     * Return the number of results on the current page of the {@link Result}.
     *
     * @return int
     */
    public function count()
    {
        return $this->getIterator()->count();
    }

    /**
     * Return the number of ALL results in the paginatable of {@link Result}.
     *
     * @return int
     */
    public function totalCount()
    {
        return $this->parentResult->count();
    }

    /**
     * Casts the result set to a PHP array.
     *
     * @return AbstractTDBMObject[]
     */
    public function toArray(): array
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
    public function map(callable $callable): MapIterator
    {
        if ($this->count() === 0) {
            return new MapIterator([], $callable);
        }
        return new MapIterator($this->getIterator(), $callable);
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
     * @return mixed Can return all value types
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
    public function offsetUnset($offset)
    {
        $this->getIterator()->offsetUnset($offset);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *               which is a value of any type other than a resource
     *
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return array_map(function (AbstractTDBMObject $item) {
            return $item->jsonSerialize();
        }, $this->toArray());
    }
}
