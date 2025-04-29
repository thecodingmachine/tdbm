<?php

declare(strict_types=1);

/*
 Copyright (C) 2006-2014 David Négrier - THE CODING MACHINE

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace TheCodingMachine\TDBM;

use WeakReference;

/**
 * The NativeWeakrefObjectStorage class is used to reference all beans that have been fetched from the database.
 * If a bean is requested twice from TDBM, the NativeWeakrefObjectStorage is used to "cache" the bean.
 * WeakReference have been added in PHP 7.4.
 *
 * @author David Negrier
 */
class NativeWeakrefObjectStorage implements ObjectStorageInterface
{
    /**
     * An array of fetched object, accessible via table name and primary key.
     * If the primary key is split on several columns, access is done by an array of columns, serialized.
     *
     * @var WeakReference[][]
     */
    private $objects = array();

    /**
     * Every 10000 set in the dataset, we perform a cleanup to ensure the WeakRef instances
     * are removed if they are no more valid.
     * This is to avoid having memory used by dangling WeakRef instances.
     *
     * @var int
     */
    private $garbageCollectorCount = 0;

    /**
     * Sets an object in the storage.
     *
     * @param string $tableName
     * @param string|int $id
     * @param DbRow  $dbRow
     */
    public function set(string $tableName, $id, DbRow $dbRow): void
    {
        $this->objects[$tableName][$id] = WeakReference::create($dbRow);
        ++$this->garbageCollectorCount;
        if ($this->garbageCollectorCount === 10000) {
            $this->garbageCollectorCount = 0;
            $this->cleanupDanglingWeakRefs();
        }
    }

    /**
     * Returns an object from the storage (or null if no object is set).
     *
     * @param string $tableName
     * @param string|int $id
     *
     * @return DbRow|null
     */
    public function get(string $tableName, $id): ?DbRow
    {
        if (isset($this->objects[$tableName][$id])) {
            return $this->objects[$tableName][$id]->get();
        }
        return null;
    }

    /**
     * Removes an object from the storage.
     *
     * @param string $tableName
     * @param string|int $id
     */
    public function remove(string $tableName, $id): void
    {
        unset($this->objects[$tableName][$id]);
    }

    /**
     * Removes all objects from the storage.
     */
    public function clear(): void
    {
        $this->objects = array();
    }

    private function cleanupDanglingWeakRefs(): void
    {
        foreach ($this->objects as $tableName => $table) {
            foreach ($table as $id => $obj) {
                if ($obj->get() === null) {
                    unset($this->objects[$tableName][$id]);
                }
            }
        }
    }
}
