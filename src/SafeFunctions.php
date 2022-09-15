<?php

namespace TheCodingMachine\TDBM;

use RuntimeException;

use function array_combine;
use function error_get_last;

class SafeFunctions
{
    /**
     * A wrapper around array_combine that never returns false.
     *
     * TODO: Remove once we support only PHP ^8.0
     *
     * @param array<int|string> $keys
     * @param mixed[] $values
     * @return mixed[]
     */
    public static function arrayCombine(array $keys, array $values): array
    {
        $array = array_combine($keys, $values);
        if ($array === false) {
            $error = error_get_last();
            throw new RuntimeException($error['message'] ?? '');
        }
        return $array;
    }
}
