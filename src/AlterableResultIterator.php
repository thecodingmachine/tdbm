<?php

namespace TheCodingMachine\TDBM;

use Porpaginas\Arrays\ArrayPage;
use Porpaginas\Iterator;
use Porpaginas\Result;

/**
 * This class acts as a wrapper around a result iterator.
 * It can be used to add or remove results from a ResultIterator (or any kind a traversable collection).
 *
 * Note: in the case of TDBM, this is useful to manage many to one relationships
 */
class AlterableResultIterator implements Result, \ArrayAccess, \JsonSerializable
{
    /**
     * @var \Iterator|null
     */
    private $resultIterator;

    /**
     * Key: the object to alter in the result set.
     * Value: "add" => the object will be added to the resultset (if it is not found in it)
     *        "delete" => the object will be removed from the resultset (if found).
     *
     * @var \SplObjectStorage
     */
    private $alterations;

    /**
     * The result array from the result set.
     *
     * @var array|null
     */
    private $resultArray;

    /**
     * @param \Iterator|null $resultIterator
     */
    public function __construct(\Iterator $resultIterator = null)
    {
        $this->resultIterator = $resultIterator;
        $this->alterations = new \SplObjectStorage();
    }

    /**
     * Sets a new iterator as the base iterator to be altered.
     *
     * @param \Iterator $resultIterator
     */
    public function setResultIterator(\Iterator $resultIterator)
    {
        $this->resultIterator = $resultIterator;
        $this->resultArray = null;
    }

    /**
     * Returns the non altered result iterator (or null if none exist).
     *
     * @return \Iterator|null
     */
    public function getUnderlyingResultIterator()
    {
        return $this->resultIterator;
    }

    /**
     * Adds an additional object to the result set (if not already available).
     *
     * @param $object
     */
    public function add($object)
    {
        $this->alterations->attach($object, 'add');

        if ($this->resultArray !== null) {
            $foundKey = array_search($object, $this->resultArray, true);
            if ($foundKey === false) {
                $this->resultArray[] = $object;
            }
        }
    }

    /**
     * Removes an object from the result set.
     *
     * @param $object
     */
    public function remove($object)
    {
        $this->alterations->attach($object, 'delete');

        if ($this->resultArray !== null) {
            $foundKey = array_search($object, $this->resultArray, true);
            if ($foundKey !== false) {
                unset($this->resultArray[$foundKey]);
            }
        }
    }

    /**
     * Casts the result set to a PHP array.
     *
     * @return array
     */
    public function toArray()
    {
        if ($this->resultArray === null) {
            if ($this->resultIterator !== null) {
                $this->resultArray = iterator_to_array($this->resultIterator);
            } else {
                $this->resultArray = [];
            }

            foreach ($this->alterations as $obj) {
                $action = $this->alterations->getInfo(); // return, if exists, associated with cur. obj. data; else NULL

                $foundKey = array_search($obj, $this->resultArray, true);

                if ($action === 'add' && $foundKey === false) {
                    $this->resultArray[] = $obj;
                } elseif ($action === 'delete' && $foundKey !== false) {
                    unset($this->resultArray[$foundKey]);
                }
            }
        }

        return array_values($this->resultArray);
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
        return isset($this->toArray()[$offset]);
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
        return $this->toArray()[$offset];
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
        throw new TDBMInvalidOperationException('You can set values in a TDBM result set, even in an alterable one. Use the add method instead.');
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
        throw new TDBMInvalidOperationException('You can unset values in a TDBM result set, even in an alterable one. Use the delete method instead.');
    }

    /**
     * @param int $offset
     *
     * @return \Porpaginas\Page
     */
    public function take($offset, $limit)
    {
        // TODO: replace this with a class implementing the map method.
        return new ArrayPage(array_slice($this->toArray(), $offset, $limit), $offset, $limit, count($this->toArray()));
    }

    /**
     * Return the number of all results in the paginatable.
     *
     * @return int
     */
    public function count()
    {
        return count($this->toArray());
    }

    /**
     * Return an iterator over all results of the paginatable.
     *
     * @return Iterator
     */
    public function getIterator()
    {
        if ($this->alterations->count() === 0) {
            if ($this->resultIterator !== null) {
                return clone $this->resultIterator;
            } else {
                return new \ArrayIterator([]);
            }
        } else {
            return new \ArrayIterator($this->toArray());
        }
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
        return $this->toArray();
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
}
