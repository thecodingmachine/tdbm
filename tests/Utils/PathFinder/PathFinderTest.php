<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils\PathFinder;

use PHPUnit\Framework\TestCase;

class PathFinderTest extends TestCase
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
