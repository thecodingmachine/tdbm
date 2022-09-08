<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM;

use Countable;
use IteratorAggregate;

interface PageInterface extends Countable, IteratorAggregate
{
    /**
     * Returns the total number of results in the paginated collection.
     */
    public function totalCount(): int;

    public function getCurrentOffset(): int;

    public function getCurrentPage(): int;

    public function getCurrentLimit(): int;
}
