<?php

namespace TheCodingMachine\TDBM\Utils;

use RuntimeException;

class StringUtils
{
    public static function getValidVariableName(string $variableName): string
    {
        $valid = preg_replace_callback('/^(\d+)/', static function (array $match) {
            $f = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
            $number = $f->format((int) $match[0]);
            return preg_replace('/[^a-z]+/i', '_', $number);
        }, $variableName);
        assert($valid !== null);
        return $valid;
    }
}
