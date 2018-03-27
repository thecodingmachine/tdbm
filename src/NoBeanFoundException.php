<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM;

/**
 * An exception thrown if no rows are returned when TDBMService->findObjectOrFail is called.
 */
class NoBeanFoundException extends TDBMException
{
}
