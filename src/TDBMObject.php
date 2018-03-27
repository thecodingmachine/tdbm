<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM;

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
 * Instances of this class represent an object that is bound to a row in a database table.
 * You access access the rows using there name, as a property of an object, or as a table.
 * For instance:
 *    <code>$tdbmObject->myrow</code>
 * or
 *    <code>$tdbmObject['myrow']</code>
 * are both valid.
 *
 * @author David Negrier
 */
class TDBMObject extends AbstractTDBMObject
{
    public function getProperty($var, $tableName = null)
    {
        return $this->get($var, $tableName);
    }

    public function setProperty($var, $value, $tableName = null)
    {
        $this->set($var, $value, $tableName);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *               which is a value of any type other than a resource
     *
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        throw new TDBMException('Json serialization is only implemented for generated beans.');
    }

    /**
     * Returns an array of used tables by this bean (from parent to child relationship).
     *
     * @return string[]
     */
    protected function getUsedTables() : array
    {
        $tableNames = array_keys($this->dbRows);
        $tableNames = $this->tdbmService->_getLinkBetweenInheritedTables($tableNames);
        $tableNames = array_reverse($tableNames);

        return $tableNames;
    }
}
