<?php
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

namespace Mouf\Database\TDBM;

use Mouf\Database\TDBM\Filters\InFilter;
use Mouf\Database\TDBM\Filters\OrderBySQLString;
use Mouf\Database\TDBM\Filters\EqualFilter;
use Mouf\Database\TDBM\Filters\SqlStringFilter;
use Mouf\Database\TDBM\Filters\AndFilter;
use Mouf\Database\DBConnection\CachedConnection;
use Mouf\Utils\Cache\CacheInterface;
use Mouf\Database\TDBM\Filters\FilterInterface;
use Doctrine\DBAL\Driver\Connection;
use Mouf\Database\DBConnection\DBConnectionException;
use Mouf\Database\TDBM\Filters\OrFilter;

/**
 * The WeakrefObjectStorage class is used to reference all beans that have been fetched from the database.
 * If a bean is requested twice from TDBM, the WeakrefObjectStorage is used to "cache" the bean.
 * Unlike the StandardObjectStorage, the WeakrefObjectStorage manages memory in a clever way, using the weakref
 * PHP extension. It is used if the "weakref" extension is available.
 * Otherwise, the StandardObjectStorage is used instead.
 *
 * @author David Negrier
 */
class WeakrefObjectStorage {
	/**
	 * An array of fetched object, accessible via table name and primary key.
	 * If the primary key is split on several columns, access is done by an array of columns, serialized.
	 * 
	 * @var array<string, WeakMap<string, TDBMObject>>
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
	 * @param string $id
	 * @param DbRow $dbRow
	 */
	public function set($tableName, $id, DbRow $dbRow) {
		$this->objects[$tableName][$id] = new \WeakRef($dbRow);
		$this->garbageCollectorCount++;
		if ($this->garbageCollectorCount == 10000) {
			$this->garbageCollectorCount = 0;
			$this->cleanupDanglingWeakRefs();
		}
	}
	
	/**
	 * Checks if an object is in the storage.
	 *
	 * @param string $tableName
	 * @param string $id
	 * @return bool
	 */
	public function has($tableName, $id) {
		if (isset($this->objects[$tableName][$id])) {
			if ($this->objects[$tableName][$id]->valid()) {
				return true;
			} else {
				unset($this->objects[$tableName][$id]);
			}
		}
		return false;
	}
	
	/**
	 * Returns an object from the storage (or null if no object is set)
	 *
	 * @param string $tableName
	 * @param string $id
	 * @return DbRow
	 */
	public function get($tableName, $id) {
		if (isset($this->objects[$tableName][$id])) {
			if ($this->objects[$tableName][$id]->valid()) {
				return $this->objects[$tableName][$id]->get();
			}
		} else {
			return null;
		}
	}
	
	/**
	 * Removes an object from the storage
	 *
	 * @param string $tableName
	 * @param string $id
	 */
	public function remove($tableName, $id) {
		unset($this->objects[$tableName][$id]);
	}
	
	/**
	 * Applies the callback to all objects.
	 * 
	 * @param callable $callback
	 */
	public function apply(callable $callback) {
		foreach ($this->objects as $tableName => $table) {
			foreach ($table as $id => $obj) {
				if ($obj->valid()) {
					$callback($obj->get(), $tableName, $id);
				} else {
					unset($this->objects[$tableName][$id]);
				}
			}
		}
	}
	
	private function cleanupDanglingWeakRefs() {
		foreach ($this->objects as $tableName => $table) {
			foreach ($table as $id => $obj) {
				if (!$obj->valid()) {
					unset($this->objects[$tableName][$id]);
				}
			}
		}
	}
}