<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM;

use Countable;
use IteratorAggregate;

interface ResultInterface extends Countable, IteratorAggregate
{
    public function take(int $offset, int $limit): PageInterface;
}
