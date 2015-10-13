<?php
namespace Mouf\Database\TDBM\Filters;

use Doctrine\DBAL\Driver\Connection;

/*
 Copyright (C) 2006-2011 David Négrier - THE CODING MACHINE

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
 * Interface for the ORDER BY statements used in TDBMService->getObjects method.
 *
 */
interface OrderByInterface {

	/**
	 * Returns a list of ORDER BY statements to be applied.
	 * Each statement will be in the form: table_name.column_name [ASC|DESC]
	 *
	 * @return array<string>
	 */
	public function toSqlStatementsArray();
	
	
	/**
	 * Returns the tables used in the order by in an array.
	 *
	 * @return array<string>
	 */
	public function getUsedTables();
}
