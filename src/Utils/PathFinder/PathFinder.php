<?php
namespace TheCodingMachine\TDBM\Utils\PathFinder;

use Mouf\Composer\ClassNameMapper;

class PathFinder implements PathFinderInterface
{
    /**
     * @var string|null
     */
    private $composerFile;

    /**
     * @var string
     */
    private $rootPath;

    /**
     * @var bool
     */
    private $useAutoloadDev;

    /**
     * @var ClassNameMapper
     */
    private $classNameMapper;

    public function __construct(string $composerFile = null, string $rootPath = null, bool $useAutoloadDev = false)
    {
        $this->composerFile = $composerFile;
        $this->useAutoloadDev = $useAutoloadDev;
        if ($rootPath === null) {
            $this->rootPath = dirname(__DIR__, 6);
        } else {
            $this->rootPath = $rootPath;
        }
    }

    private function getClassNameMapper() : ClassNameMapper
    {
        if ($this->classNameMapper === null) {
            $this->classNameMapper = ClassNameMapper::createFromComposerFile($this->composerFile, $this->rootPath, $this->useAutoloadDev);
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
        return new \SplFileInfo($this->rootPath.'/'.$paths[0]);
    }
}
