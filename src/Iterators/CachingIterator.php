<?php


namespace TheCodingMachine\TDBM\Iterators;


use Countable;
use Iterator;
use TheCodingMachine\TDBM\AbstractTDBMObject;
use TheCodingMachine\TDBM\InnerResultIteratorInterface;
use TheCodingMachine\TDBM\TDBMInvalidOffsetException;
use TheCodingMachine\TDBM\TDBMInvalidOperationException;
use Traversable;
use function current;
use function filter_var;
use function get_class;
use function key;
use function next;
use const FILTER_VALIDATE_INT;

/**
 * An iterator that caches results (just like \CachingIterator), but that also accepts seeking a value even if
 * iteration did not started yet.
 */
class CachingIterator implements Iterator, InnerResultIteratorInterface
{
    /**
     * The list of results already fetched.
     *
     * @var AbstractTDBMObject[]
     */
    private $results = [];
    /**
     * @var Traversable
     */
    private $iterator;
    /**
     * @var bool
     */
    private $fetchStarted = false;
    /**
     * @var mixed
     */
    private $current;
    /**
     * @var int
     */
    private $key;

    public function __construct(Traversable $iterator)
    {
        $this->iterator = $iterator;
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
        try {
            $this->toIndex($offset);
        } catch (TDBMInvalidOffsetException $e) {
            return false;
        }

        return true;
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
        $this->toIndex($offset);

        return $this->results[$offset];
    }

    /**
     * @param mixed $offset
     * @throws TDBMInvalidOffsetException
     */
    private function toIndex($offset): void
    {
        if ($offset < 0 || filter_var($offset, FILTER_VALIDATE_INT) === false) {
            throw new TDBMInvalidOffsetException('Trying to access result set using offset "'.$offset.'". An offset must be a positive integer.');
        }
        if (!$this->fetchStarted) {
            $this->rewind();
        }
        while (!isset($this->results[$offset])) {
            $this->next();
            if ($this->current === null) {
                throw new TDBMInvalidOffsetException('Offset "'.$offset.'" does not exist in result set.');
            }
        }
    }

    public function next()
    {
        // Let's overload the next() method to store the result.
        if (isset($this->results[$this->key + 1])) {
            ++$this->key;
            $this->current = $this->results[$this->key];
        } else {
            $this->current = next($this->iterator);
            $this->key = key($this->iterator);
            if ($this->key !== null) {
                $this->results[$this->key] = $this->current;
            }
        }
    }

    /**
     * Do not reexecute the query.
     */
    public function rewind()
    {
        if (!$this->fetchStarted) {
            reset($this->iterator);
            $this->fetchStarted = true;
            $this->key = key($this->iterator);
            $this->current = current($this->iterator);
            $this->results[$this->key] = $this->current;
        } else {
            $this->key = 0;
            $this->current = $this->results[0];
        }
    }

    /**
     * Return the current element
     * @link https://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        return $this->current;
    }

    /**
     * Return the key of the current element
     * @link https://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return $this->key;
    }

    /**
     * Checks if current position is valid
     * @link https://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        return $this->key !== null;
    }

    /**
     * Offset to set
     * @link https://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        throw new TDBMInvalidOperationException('You cannot set values in a TDBM result set.');
    }

    /**
     * Offset to unset
     * @link https://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        throw new TDBMInvalidOperationException('You cannot unset values in a TDBM result set.');
    }

    /**
     * Count elements of an object
     * @link https://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        if ($this->iterator instanceof Countable) {
            return $this->iterator->count();
        }
        throw new TDBMInvalidOperationException('Cannot count items of iterator '.get_class($this->iterator));
    }
}
