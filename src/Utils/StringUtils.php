<?php

namespace TheCodingMachine\TDBM\Utils;

class StringUtils
{
    public static function getValidVariableName(string $variableName): string
    {
        return preg_replace_callback('/^(\d+)/', static function (array $match) {
            $f = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
            $number = $f->format((int) $match[0]);
            return preg_replace('/[^a-z]+/i', '_', $number);
        }, $variableName);
    }
}
