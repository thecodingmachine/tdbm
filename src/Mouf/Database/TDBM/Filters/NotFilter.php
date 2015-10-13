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
 * The NotFilter class translates into an "NOT" SQL statement: it reverses the filter.
 * 
 * @Component
 * @author David Négrier
 */
class NotFilter implements FilterInterface {
	private $filter;

	/**
	 * The filter the not will be applied to.
	 * 
	 * @Property
	 * @Compulsory
	 * @param FilterInterface $filter
	 */
	public function setFilter(FilterInterface $filter) {
		$this->filter = $filter;
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
	 * @param FilterInterface $filter
	 */
	public function __construct($filter=null) {
		$this->filter = $filter;
	}

	/**
	 * Returns the SQL of the filter (the SQL WHERE clause).
	 *
	 * @param Connection $dbConnection
	 * @return string
	 */
	public function toSql(Connection $dbConnection) {
		if ($this->enableCondition != null && !$this->enableCondition->isOk()) {
			return "";
		}
		

		return 'NOT ('.$this->filter->toSql($dbConnection).')';
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
		return $this->filter->getUsedTables();
	}
}
