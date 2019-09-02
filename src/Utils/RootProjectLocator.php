<?php


namespace TheCodingMachine\TDBM\Utils;

class RootProjectLocator
{
    public static function getRootLocationPath(): string
    {
        $reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
        return dirname($reflection->getFileName(), 3).'/';
    }
}
