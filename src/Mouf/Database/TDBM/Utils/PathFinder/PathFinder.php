<?php
namespace Mouf\Database\TDBM\Utils\PathFinder;

use Mouf\Composer\ClassNameMapper;

class PathFinder implements PathFinderInterface
{
    /**
     * @var string
     */
    private $composerFile;

    /**
     * @var bool
     */
    private $useAutoloadDev;

    /**
     * @var ClassNameMapper
     */
    private $classNameMapper;

    public function __construct(string $composerFile = null, bool $useAutoloadDev = false)
    {
        $this->composerFile = $composerFile;
        $this->useAutoloadDev = $useAutoloadDev;
    }

    private function getClassNameMapper() : ClassNameMapper
    {
        if ($this->classNameMapper === null) {
            $this->classNameMapper = ClassNameMapper::createFromComposerFile($this->composerFile, null, $this->useAutoloadDev);
        }
        return $this->classNameMapper;
    }

    /**
     * Returns the path of a class file given the fully qualified class name.
     *
     * @param string $className
     * @return \SplFileInfo
     * @throws NoPathFoundException
     */
    public function getPath(string $className): \SplFileInfo
    {
        $paths = $this->getClassNameMapper()->getPossibleFileNames($className);
        if (empty($paths)) {
            throw NoPathFoundException::create($className);
        }
        return new \SplFileInfo($paths[0]);
    }
}
