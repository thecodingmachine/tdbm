<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM;

use ArrayIterator;
use Traversable;

class PageArray implements PageInterface
{
    private array $items;
    private int $offset;
    private int $limit;
    private int $totalCount;

    /**
     * @param array $items
     */
    public function __construct(array $items, int $offset, int $limit, int $totalCount)
    {
        $this->items = $items;
        $this->offset = $offset;
        $this->limit = $limit;
        $this->totalCount = $totalCount;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function totalCount(): int
    {
        return $this->totalCount;
    }

    public function getCurrentOffset(): int
    {
        return $this->offset;
    }

    public function getCurrentPage(): int
    {
        return (int) floor($this->offset / $this->limit) + 1;
    }

    public function getCurrentLimit(): int
    {
        return $this->limit;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
