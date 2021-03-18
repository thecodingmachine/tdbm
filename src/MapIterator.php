<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM;

use Iterator;
use IteratorAggregate;
use Traversable;

/**
 * An iterator that maps element of another iterator by calling a callback on it.
 */
class MapIterator implements Iterator, \JsonSerializable
{
    /**
     * @var Iterator
     */
    protected $iterator;

    /**
     * @var callable Modifies the current item in iterator
     */
    protected $callable;

    /**
     * @param Traversable|array $iterator
     * @param callable $callable This can have two parameters
     *
     * @throws TDBMException
     */
    public function __construct($iterator, callable $callable)
    {
        if (is_array($iterator)) {
            $this->iterator = new \ArrayIterator($iterator);
        } elseif ($iterator instanceof Iterator) {
            $this->iterator = $iterator;
        } elseif ($iterator instanceof IteratorAggregate) {
            while (!$iterator instanceof Iterator) {
                $iterator = $iterator->getIterator();
            }
            $this->iterator = $iterator;
        } else {
            throw new TDBMException('$iterator parameter must be an instance of Iterator');
        }

        if ($callable instanceof \Closure) {
            // make sure there's one argument
            $reflection = new \ReflectionObject($callable);
            if ($reflection->hasMethod('__invoke')) {
                $method = $reflection->getMethod('__invoke');
                if ($method->getNumberOfParameters() !== 1) {
                    throw new TDBMException('$callable must accept one and only one parameter.');
                }
            }
        }

        $this->callable = $callable;
    }

    /**
     * Alters the current item with $this->callable and returns a new item.
     * Be careful with your types as we can't do static type checking here!
     *
     * @return mixed
     */
    public function current()
    {
        $callable = $this->callable;

        return $callable($this->iterator->current());
    }

    public function next()
    {
        $this->iterator->next();
    }

    /**
     * @return mixed
     */
    public function key()
    {
        return $this->iterator->key();
    }

    public function valid()
    {
        return $this->iterator->valid();
    }

    public function rewind()
    {
        $this->iterator->rewind();
    }

    /**
     * Casts the iterator to a PHP array.
     *
     * @return mixed[]
     */
    public function toArray(): array
    {
        return iterator_to_array($this);
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
