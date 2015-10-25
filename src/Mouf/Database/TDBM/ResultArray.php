<?php
namespace Mouf\Database\TDBM;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Statement;
use Mouf\Database\MagicQuery;

/*
 Copyright (C) 2006-2015 David Négrier - THE CODING MACHINE

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
 * Iterator used to retrieve results. It behaves like an array.
 *
 */
class ResultArray extends ResultIterator implements \ArrayAccess {

	/**
	 * The list of results already fetched.
	 *
	 * @var AbstractTDBMObject[]
	 */
	private $results = [];

	/**
	 * Whether a offset exists
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 * @param mixed $offset <p>
	 * An offset to check for.
	 * </p>
	 * @return boolean true on success or false on failure.
	 * </p>
	 * <p>
	 * The return value will be casted to boolean if non-boolean was returned.
	 * @since 5.0.0
	 */
	public function offsetExists($offset)
	{
		try {
			$this->toIndex($offset);
		} catch (TDBMInvalidOffsetException $e) {
			return false;
		}

		return true;
	}

	/**
	 * Offset to retrieve
	 * @link http://php.net/manual/en/arrayaccess.offsetget.php
	 * @param mixed $offset <p>
	 * The offset to retrieve.
	 * </p>
	 * @return mixed Can return all value types.
	 * @since 5.0.0
	 */
	public function offsetGet($offset)
	{
		$this->toIndex($offset);
		return $this->results[$offset];
	}

	/**
	 * Offset to set
	 * @link http://php.net/manual/en/arrayaccess.offsetset.php
	 * @param mixed $offset <p>
	 * The offset to assign the value to.
	 * </p>
	 * @param mixed $value <p>
	 * The value to set.
	 * </p>
	 * @return void
	 * @since 5.0.0
	 */
	public function offsetSet($offset, $value)
	{
		throw new TDBMInvalidOperationException('You can set values in a TDBM result set.');
	}

	/**
	 * Offset to unset
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 * @param mixed $offset <p>
	 * The offset to unset.
	 * </p>
	 * @return void
	 * @since 5.0.0
	 */
	public function offsetUnset($offset)
	{
		throw new TDBMInvalidOperationException('You can unset values in a TDBM result set.');
	}

	private function toIndex($offset) {
		if ($offset < 0 || filter_var($offset, FILTER_VALIDATE_INT) === false) {
			throw new TDBMInvalidOffsetException('Trying to access result set using offset "'.$offset.'". An offset must be a positive integer.');
		}
		while (!isset($this->results[$offset])) {
			$this->next();
			if ($this->current === null) {
				throw new TDBMInvalidOffsetException('Offset "'.$offset.'" does not exist in result set.');
			}
		}
	}

	public function next() {
		// Let's overload the next() method to store the result.
		if (isset($this->results[$this->key+1])) {
			$this->key++;
			$this->current = $this->results[$this->key];
		} else {
			parent::next();
			$this->results[$this->key] = $this->current;
		}
	}

	/**
	 * Overloads the rewind implementation.
	 * Do not reexecute the query.
	 */
	public function rewind()
	{
		if (!$this->fetchStarted) {
			$this->executeQuery();
		}
		$this->key = -1;
		$this->next();
	}

	/**
	 * Resets the query.
	 * Stops any running query. Does not run the query again.
	 *
	 * @param bool $resetTotalCount
	 */
	protected function reset($resetTotalCount = false) {
		$this->results = [];
		parent::reset($resetTotalCount);
	}
}
