<?php


namespace TheCodingMachine\TDBM\Utils;

use function array_keys;
use function count;
use function implode;
use function is_array;
use function range;
use function var_export;

class Psr2Utils
{
    /**
     * @param mixed $var
     * @param string $indent
     * @return string
     */
    public static function psr2VarExport($var, string $indent=''): string
    {
        if (is_array($var)) {
            $indexed = array_keys($var) === range(0, count($var) - 1);
            $r = [];
            foreach ($var as $key => $value) {
                $r[] = "$indent    "
                    . ($indexed ? '' : self::psr2VarExport($key) . ' => ')
                    . self::psr2VarExport($value, "$indent    ");
            }
            return "[\n" . implode(",\n", $r) . "\n" . $indent . ']';
        }
        return var_export($var, true);
    }

    /**
     * @param mixed $var
     * @return string
     */
    public static function psr2InlineVarExport($var): string
    {
        if (is_array($var)) {
            $indexed = array_keys($var) === range(0, count($var) - 1);
            $r = [];
            foreach ($var as $key => $value) {
                $r[] = ($indexed ? '' : self::psr2InlineVarExport($key) . ' => ')
                    . self::psr2InlineVarExport($value);
            }
            return '[' . implode(',', $r) . ']';
        }
        return var_export($var, true);
    }
}
