<?php


namespace TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad;

/**
 * An object that can be used to store data loaded with the DataLoader pattern
 */
interface StorageNode
{
    public function getManyToOneDataLoader(string $key): ManyToOneDataLoader;

    public function hasManyToOneDataLoader(string $key): bool;

    public function setManyToOneDataLoader(string $key, ManyToOneDataLoader $manyToOneDataLoader): void;

    // TODO: getManyToOneDataLoader($key) / hasManyToOneDataLoader($key) / setManyToOneDataLoader($key) ...
    // ... getOneToManyDataLoader ...
}