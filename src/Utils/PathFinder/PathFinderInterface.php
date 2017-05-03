<?php


namespace TheCodingMachine\TDBM\Utils\PathFinder;

interface PathFinderInterface
{
    /**
     * Returns the path of a class file given the fully qualified class name.
     *
     * @param string $className
     * @return \SplFileInfo
     * @throws NoPathFoundException
     */
    public function getPath(string $className) : \SplFileInfo;
}
