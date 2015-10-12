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
use Mouf\Database\TDBM\Filters\FilterInterface;
use Mouf\Database\DBConnection\ConnectionInterface;


/**
 * Instances of this class represent an object that is bound to a row in a database table.
 * You access access the rows using there name, as a property of an object, or as a table.
 * For instance:
 * 	<code>$tdbmObject->myrow</code>
 * or
 * 	<code>$tdbmObject['myrow']</code>
 * are both valid.
 * 
 * @author David Negrier
 */
class TDBMObject extends AbstractTDBMObject {

	public function __get($var) {
		return $this->get($var);
	}

	/**
	 * Returns true if a column is set, false otherwise.
	 * 
	 * @param string $var
	 * @return boolean
	 */
	public function __isset($var) {
		return $this->has($var);
	}
	
	public function __set($var, $value) {
		$this->set($var, $value);
	}

}