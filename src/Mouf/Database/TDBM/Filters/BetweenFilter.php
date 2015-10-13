<?php
namespace Mouf\Database\TDBM\Filters;

use Mouf\Database\TDBM\TDBMException;

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
 * The BetweenFilter class translates into an "BETWEEN" SQL statement.
 * 
 * @Component
 * @author David Négrier
 */
class BetweenFilter implements FilterInterface {
	private $tableName;
	private $columnName;
	private $value1;
	private $value2;
	
	/**
	 * The table name (or alias if any) to use in the filter.
	 * 
	 * @Property
	 * @Compulsory
	 * @param string $tableName
	 */
	public function setTableName($tableName) {
		$this->tableName = $tableName;
	}

	/**
	 * The column name (or alias if any) to use in the filter.
	 * 
	 * @Property
	 * @Compulsory
	 * @param string $columnName
	 */
	public function setColumnName($columnName) {
		$this->columnName = $columnName;
	}

	/**
	 * The lower bound value to compare to in the between filter.
	 * 
	 * @Property
	 * @Compulsory
	 * @param string $value1
	 */
	public function setValue1($value1) {
		$this->value1 = $value1;
	}

	/**
	 * The higher bound value to compare to in the between filter.
	 * 
	 * @Property
	 * @Compulsory
	 * @param string $value2
	 */
	public function setValue2($value2) {
		$this->value2 = $value2;
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
	 * @param string $tableName
	 * @param string $columnName
     * @param string $value1
     * @param string $value2
	 */
	public function __construct($tableName=null, $columnName=null, $value1=null, $value2=null) {
		$this->tableName = $tableName;
		$this->columnName = $columnName;
		$this->value1 = $value1;
		$this->value2 = $value2;		
	}

	/**
	 * Returns the SQL of the filter (the SQL WHERE clause).
	 *
	 * @param Connection $dbConnection
	 * @return string
     * @throws \Mouf\Database\TDBM\TDBMException
	 */
    public function toSql(Connection $dbConnection) {
		if ($this->enableCondition != null && !$this->enableCondition->isOk()) {
			return "";
		}
		

		if ($this->value1 === null || $this->value2 === null) {
			throw new TDBMException('Error in BetweenFilter: one of the value passed is NULL.');
		}

		return $this->tableName.'.'.$this->columnName.' BETWEEN '.FilterUtils::valueToSql($this->value1, $dbConnection)." AND ".FilterUtils::valueToSql($this->value2, $dbConnection);
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
		return array($this->tableName);
	}
}
