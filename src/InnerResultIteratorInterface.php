<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM;

/**
 * Interface implemented by all "inner result iterators"
 */
interface InnerResultIteratorInterface extends \Traversable, \Countable, \ArrayAccess {}
