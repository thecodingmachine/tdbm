<?php
namespace Mouf\Database\TDBM;

use Iterator;


/**
 * An iterator that maps element of another iterator by calling a callback on it.
 */
class MapIterator implements Iterator {

    /**
     * @var Iterator
     */
    protected $iterator;

    /**
     * @var callable Modifies the current item in iterator
     */
    protected $callable;

    /**
     * @param $iterator Iterator|array
     * @param $callable callable This can have two parameters
     * @throws TDBMException
     */
    public function __construct($iterator, callable $callable) {
        if (is_array($iterator)) {
            $this->iterator = new \ArrayIterator($iterator);
        }
        elseif (!($iterator instanceof Iterator))
        {
            throw new TDBMException("\$iterator parameter must be an instance of Iterator");
        }
        else
        {
            $this->iterator = $iterator;
        }

        if ($callable instanceof \Closure) {
            // make sure there's one argument
            $reflection = new \ReflectionObject($callable);
            if ($reflection->hasMethod('__invoke')) {
                $method = $reflection->getMethod('__invoke');
                if ($method->getNumberOfParameters() !== 1) {
                    throw new TDBMException("\$callable must accept one and only one parameter.");
                }
            }
        }

        $this->callable = $callable;
    }

    /**
     * Alters the current item with $this->callable and returns a new item.
     * Be careful with your types as we can't do static type checking here!
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
     * @return array
     */
    public function toArray() {
        return iterator_to_array($this);
    }
}
