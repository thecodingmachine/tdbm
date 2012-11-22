<?php
namespace Mouf\Database\TDBM;

/*
 Copyright (C) 2006-2009 David Négrier - THE CODING MACHINE

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


/**
 * An object that behaves just like an array of TDBMObjects.
 * If there is only one object in it, it can be accessed just like an object.
 *
 */
class TDBMObjectArray extends \ArrayObject {
	public function __get($var) {
		$cnt = count($this);
		if ($cnt==1)
		{
			return $this[0]->__get($var);
		}
		elseif ($cnt>1)
		{
			throw new TDBMException('Array contains many objects! Use getarray_'.$var.' to retrieve an array of '.$var);
		}
		else
		{
			throw new TDBMException('Array contains no objects');
		}
	}

	public function __set($var, $value) {
		$cnt = count($this);
		if ($cnt==1)
		{
			return $this[0]->__set($var, $value);
		}
		elseif ($cnt>1)
		{
			throw new TDBMException('Array contains many objects! Use setarray_'.$var.' to set the array of '.$var);
		}
		else
		{
			throw new TDBMException('Array contains no objects');
		}
	}

	/**
	 * getarray_column_name returns an array containing the values of the column of the given objects.
	 * setarray_column_name sets the value of the given column for all the objects.
	 *
	 * @param unknown_type $func_name
	 * @param unknown_type $values
	 * @return unknown
	 */
	public function __call($func_name, $values) {

		if (strpos($func_name,"getarray_") === 0) {
			$column = substr($func_name, 9);
			return $this->getarray($column);
		} elseif (strpos($func_name,"setarray_") === 0) {
			$column = substr($func_name, 9);
			return $this->setarray($column, $values[0]);
		} elseif (count($this)==1) {
			$this[0]->__call($func_name, $values);
		}
		else
		{
			throw new TDBMException("Method ".$func_name." not found");
		}

	}

	private function getarray($column) {
		$arr = array();
		foreach ($this as $object) {
			$arr[] = $object->__get($column);
		}
		return $arr;
	}

	private function setarray($column, $value) {
		foreach ($this as $object) {
			$object->__set($column, $value);
		}
	}


}

?>