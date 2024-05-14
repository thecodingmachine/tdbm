<?php

namespace TheCodingMachine\TDBM;

use Psr\Log\NullLogger;
use TheCodingMachine\TDBM\ResultIterator;

class EmptyResultIterator extends ResultIterator
{
    protected function __construct()
    {
        $this->totalCount = 0;
        $this->logger = new NullLogger();
    }

}
