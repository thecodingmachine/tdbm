<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM;

/**
 * An exception thrown if 2 rows are returned when TDBMService->getObject is called.
 * This can only happen if you use a filter bag as second parameter to the getObject method.
 */
class DuplicateRowException extends TDBMException
{
}
