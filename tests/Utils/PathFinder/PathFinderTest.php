<?php

namespace TheCodingMachine\TDBM\Utils\PathFinder;

class PathFinderTest extends \PHPUnit_Framework_TestCase
{
    public function testGetPath()
    {
        $pathFinder = new PathFinder(null, dirname(__DIR__, 6));
        $path = realpath($pathFinder->getPath(PathFinder::class)->getPathname());
        $expectedPath = (new \ReflectionClass(PathFinder::class))->getFileName();

        $this->assertSame($expectedPath, $path);
    }

    public function testGetPathNotFound()
    {
        $pathFinder = new PathFinder(null, dirname(__DIR__, 6));
        $this->expectException(NoPathFoundException::class);
        $pathFinder->getPath("Not\\Exist");
    }
}
