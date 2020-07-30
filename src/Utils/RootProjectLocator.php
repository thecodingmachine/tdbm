<?php


namespace TheCodingMachine\TDBM\Utils;

class RootProjectLocator
{
    public static function getRootLocationPath(): string
    {
        $reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
        $file = $reflection->getFileName();
        assert($file !== false);
        return dirname($file, 3).'/';
    }
}
