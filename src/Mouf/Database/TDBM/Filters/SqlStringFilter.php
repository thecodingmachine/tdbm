<?php
namespace Mouf\Database\TDBM\Filters;

use Mouf\Database\DBConnection\ConnectionInterface;

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
 * The SqlStringFilter class is directly put in the WHERE clause.
 * IMPORTANT! When you refer a column in this class, you should refer it as [table_name].[column_name].
 * Qualifying the column name with the table name will help TDBM know that the table is used and will allow
 * TDBM to infer the FROM section of the SELECT statement from the WHERE clause.
 * 
 * @Component
 * @author David Négrier
 */
class SqlStringFilter implements FilterInterface {
	private $sqlString;
	
	/**
	 * The SQL string to put in the where clause.
	 * 
	 * @Property
	 * @Compulsory
	 * @param string $sqlString
	 */
	public function setSqlString($sqlString) {
		$this->sqlString = $sqlString;
	}
	
	private $enableCondition;
	
	/**
	 * You can use an object implementing the ConditionInterface to activate this filter conditionnally.
	 * If you do not specify any condition, the filter will always be used.
	 *
	 * @param ConditionInterface $enableCondition
	 */
	public function setEnableCondition($enableCondition) {
		$this->enableCondition = $enableCondition;
	}
	
	/**
	 * Default constructor to build the filter.
	 * All parameters are optional and can later be set using the setters.
	 * 
	 * @param string $sqlString
	 */
	public function __construct($sqlString=null) {
		$this->sqlString = $sqlString;
	}

	/**
	 * Returns the SQL of the filter (the SQL WHERE clause).
	 *
	 * @param ConnectionInterface $dbConnection
	 * @return string
	 */
	public function toSql(ConnectionInterface $dbConnection) {
		if ($this->enableCondition != null && !$this->enableCondition->isOk()) {
			return "";
		}
		

		return $this->sqlString;
	}

	/**
	 * Returns the tables used in the filter in an array.
	 *
	 * @return array<string>
	 */
	public function getUsedTables() {
		if ($this->enableCondition != null && !$this->enableCondition->isOk()) {
			return array();
		}
		// Let's parse the SQL string and find all xxx.yyy tokens not enclosed in quotes.

		// First, let's remove all the stuff in quotes:

		// Let's remove all the \' found
		$work_str = str_replace("\\'",'',$this->sqlString);
		// Now, let's split the string using '
		$work_table = explode("'", $work_str);

		if (count($work_table)==0)
		return '';

		// if we start with a ', let's remove the first text
		if (strstr($work_str,"'")===0)
		array_shift($work_table);
			
		if (count($work_table)==0)
		return '';

		// Now, let's take only the stuff outside the quotes.
		$work_str2 = '';

		$i=0;
		foreach ($work_table as $str_fragment) {
			if (($i % 2) == 0)
			$work_str2 .= $str_fragment.' ';
			$i++;
		}

		// Now, let's run a regexp to find all the strings matching the pattern xxx.yyy
		preg_match_all('/([a-zA-Z_](?:[a-zA-Z0-9_]*))\.(?:[a-zA-Z_](?:[a-zA-Z0-9_]*))/', $work_str2,$capture_result);

		$tables_used = $capture_result[1];
		// remove doubles:
		$tables_used = array_flip(array_flip($tables_used));
		return $tables_used;
	}
}
