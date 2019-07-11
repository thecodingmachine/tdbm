<?php


namespace TheCodingMachine\TDBM;

use function array_combine;
use function error_get_last;
use RuntimeException;

class SafeFunctions
{
    /**
     * A wrapper around array_combine that never returns false.
     *
     * @param array<int|string> $keys
     * @param mixed[] $values
     * @return mixed[]
     */
    public static function arrayCombine(array $keys, array $values): array
    {
        $array = array_combine($keys, $values);
        if ($array === false) {
            throw new RuntimeException(error_get_last()['message']);
        }
        return $array;
    }
}
