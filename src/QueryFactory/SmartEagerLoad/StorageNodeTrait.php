<?php


namespace TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad;

trait StorageNodeTrait
{
    /**
     * @var array<string, ManyToOneDataLoader>
     */
    private $manyToOneDataLoaders = [];

    public function getManyToOneDataLoader(string $key): ManyToOneDataLoader
    {
        return $this->manyToOneDataLoaders[$key];
    }

    public function hasManyToOneDataLoader(string $key): bool
    {
        return isset($this->manyToOneDataLoaders[$key]);
    }

    public function setManyToOneDataLoader(string $key, ManyToOneDataLoader $manyToOneDataLoader): void
    {
        $this->manyToOneDataLoaders[$key] = $manyToOneDataLoader;
    }
}
