<?php

namespace Mouf\Database\TDBM\Utils\PathFinder;


class PathFinderTest extends \PHPUnit_Framework_TestCase
{
    public function testGetPath()
    {
        $pathFinder = new PathFinder();
        $this->assertSame('src/Mouf/Database/TDBM/Utils/PathFinder/PathFinder.php', $pathFinder->getPath(PathFinder::class)->getPathname());
    }

    public function testGetPathNotFound()
    {
        $pathFinder = new PathFinder();
        $this->expectException(NoPathFoundException::class);
        $pathFinder->getPath("Not\\Exist");
    }
}
