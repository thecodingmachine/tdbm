<?php

namespace Mouf\Database\TDBM;

/*
 Copyright (C) 2006-2016 David Négrier - THE CODING MACHINE

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
 * Instances of this class represent a row in a database.
 *
 * @author David Negrier
 */
class DbRow
{
    /**
     * The service this object is bound to.
     *
     * @var TDBMService
     */
    protected $tdbmService;

    /**
     * The object containing this db row.
     *
     * @var AbstractTDBMObject
     */
    private $object;

    /**
     * The name of the table the object if issued from.
     *
     * @var string
     */
    private $dbTableName;

    /**
     * The array of columns returned from database.
     *
     * @var array
     */
    private $dbRow = array();

    /**
     * @var AbstractTDBMObject[]
     */
    private $references = array();

    /**
     * One of TDBMObjectStateEnum::STATE_NEW, TDBMObjectStateEnum::STATE_NOT_LOADED, TDBMObjectStateEnum::STATE_LOADED, TDBMObjectStateEnum::STATE_DELETED.
     * $status = TDBMObjectStateEnum::STATE_NEW when a new object is created with DBMObject:getNewObject.
     * $status = TDBMObjectStateEnum::STATE_NOT_LOADED when the object has been retrieved with getObject but when no data has been accessed in it yet.
     * $status = TDBMObjectStateEnum::STATE_LOADED when the object is cached in memory.
     *
     * @var string
     */
    private $status;

    /**
     * The values of the primary key.
     * This is set when the object is in "loaded" state.
     *
     * @var array An array of column => value
     */
    private $primaryKeys;

    /**
     * You should never call the constructor directly. Instead, you should use the
     * TDBMService class that will create TDBMObjects for you.
     *
     * Used with id!=false when we want to retrieve an existing object
     * and id==false if we want a new object
     *
     * @param AbstractTDBMObject $object      The object containing this db row.
     * @param string             $table_name
     * @param array              $primaryKeys
     * @param TDBMService        $tdbmService
     *
     * @throws TDBMException
     * @throws TDBMInvalidOperationException
     */
    public function __construct(AbstractTDBMObject $object, $table_name, array $primaryKeys = array(), TDBMService $tdbmService = null, array $dbRow = array())
    {
        $this->object = $object;
        $this->dbTableName = $table_name;

        $this->status = TDBMObjectStateEnum::STATE_DETACHED;

        if ($tdbmService === null) {
            if (!empty($primaryKeys)) {
                throw new TDBMException('You cannot pass an id to the DbRow constructor without passing also a TDBMService.');
            }
        } else {
            $this->tdbmService = $tdbmService;

            if (!empty($primaryKeys)) {
                $this->_setPrimaryKeys($primaryKeys);
                if (!empty($dbRow)) {
                    $this->dbRow = $dbRow;
                    $this->status = TDBMObjectStateEnum::STATE_LOADED;
                } else {
                    $this->status = TDBMObjectStateEnum::STATE_NOT_LOADED;
                }
                $tdbmService->_addToCache($this);
            } else {
                $this->status = TDBMObjectStateEnum::STATE_NEW;
                $this->tdbmService->_addToToSaveObjectList($this);
            }
        }
    }

    public function _attach(TDBMService $tdbmService)
    {
        if ($this->status !== TDBMObjectStateEnum::STATE_DETACHED) {
            throw new TDBMInvalidOperationException('Cannot attach an object that is already attached to TDBM.');
        }
        $this->tdbmService = $tdbmService;
        $this->status = TDBMObjectStateEnum::STATE_NEW;
        $this->tdbmService->_addToToSaveObjectList($this);
    }

    /**
     * Sets the state of the TDBM Object
     * One of TDBMObjectStateEnum::STATE_NEW, TDBMObjectStateEnum::STATE_NOT_LOADED, TDBMObjectStateEnum::STATE_LOADED, TDBMObjectStateEnum::STATE_DELETED.
     * $status = TDBMObjectStateEnum::STATE_NEW when a new object is created with DBMObject:getNewObject.
     * $status = TDBMObjectStateEnum::STATE_NOT_LOADED when the object has been retrieved with getObject but when no data has been accessed in it yet.
     * $status = TDBMObjectStateEnum::STATE_LOADED when the object is cached in memory.
     *
     * @param string $state
     */
    public function _setStatus($state)
    {
        $this->status = $state;
    }

    /**
     * This is an internal method. You should not call this method yourself. The TDBM library will do it for you.
     * If the object is in state 'not loaded', this method performs a query in database to load the object.
     *
     * A TDBMException is thrown is no object can be retrieved (for instance, if the primary key specified
     * cannot be found).
     */
    public function _dbLoadIfNotLoaded()
    {
        if ($this->status == TDBMObjectStateEnum::STATE_NOT_LOADED) {
            $connection = $this->tdbmService->getConnection();

            /// buildFilterFromFilterBag($filter_bag)
            list($sql_where, $parameters) = $this->tdbmService->buildFilterFromFilterBag($this->primaryKeys);

            $sql = 'SELECT * FROM '.$connection->quoteIdentifier($this->dbTableName).' WHERE '.$sql_where;
            $result = $connection->executeQuery($sql, $parameters);

            if ($result->rowCount() === 0) {
                throw new TDBMException("Could not retrieve object from table \"$this->dbTableName\" using filter \"\".");
            }

            $this->dbRow = $result->fetch(\PDO::FETCH_ASSOC);

            $result->closeCursor();

            $this->status = TDBMObjectStateEnum::STATE_LOADED;
        }
    }

    public function get($var)
    {
        $this->_dbLoadIfNotLoaded();

        // Let's first check if the key exist.
        if (!isset($this->dbRow[$var])) {
            /*
            // Unable to find column.... this is an error if the object has been retrieved from database.
            // If it's a new object, well, that may not be an error after all!
            // Let's check if the column does exist in the table
            $column_exist = $this->db_connection->checkColumnExist($this->dbTableName, $var);
            // If the column DOES exist, then the object is new, and therefore, we should
            // return null.
            if ($column_exist === true) {
                return null;
            }

            // Let's try to be accurate in error reporting. The checkColumnExist returns an array of closest matches.
            $result_array = $column_exist;

            if (count($result_array)==1)
            $str = "Could not find column \"$var\" in table \"$this->dbTableName\". Maybe you meant this column: '".$result_array[0]."'";
            else
            $str = "Could not find column \"$var\" in table \"$this->dbTableName\". Maybe you meant one of those columns: '".implode("', '",$result_array)."'";

            throw new TDBMException($str);*/
            return;
        }

        return $this->dbRow[$var];
    }

    /**
     * Returns true if a column is set, false otherwise.
     *
     * @param string $var
     *
     * @return bool
     */
    /*public function has($var) {
        $this->_dbLoadIfNotLoaded();

        return isset($this->dbRow[$var]);
    }*/

    public function set($var, $value)
    {
        $this->_dbLoadIfNotLoaded();

        /*
        // Ok, let's start by checking the column type
        $type = $this->db_connection->getColumnType($this->dbTableName, $var);

        // Throws an exception if the type is not ok.
        if (!$this->db_connection->checkType($value, $type)) {
            throw new TDBMException("Error! Invalid value passed for attribute '$var' of table '$this->dbTableName'. Passed '$value', but expecting '$type'");
        }
        */

        /*if ($var == $this->getPrimaryKey() && isset($this->dbRow[$var]))
            throw new TDBMException("Error! Changing primary key value is forbidden.");*/
        $this->dbRow[$var] = $value;
        if ($this->tdbmService !== null && $this->status === TDBMObjectStateEnum::STATE_LOADED) {
            $this->status = TDBMObjectStateEnum::STATE_DIRTY;
            $this->tdbmService->_addToToSaveObjectList($this);
        }
    }

    /**
     * @param string             $foreignKeyName
     * @param AbstractTDBMObject $bean
     */
    public function setRef($foreignKeyName, AbstractTDBMObject $bean = null)
    {
        $this->references[$foreignKeyName] = $bean;

        if ($this->tdbmService !== null && $this->status === TDBMObjectStateEnum::STATE_LOADED) {
            $this->status = TDBMObjectStateEnum::STATE_DIRTY;
            $this->tdbmService->_addToToSaveObjectList($this);
        }
    }

    /**
     * @param string $foreignKeyName A unique name for this reference
     *
     * @return AbstractTDBMObject|null
     */
    public function getRef($foreignKeyName)
    {
        if (isset($this->references[$foreignKeyName])) {
            return $this->references[$foreignKeyName];
        } elseif ($this->status === TDBMObjectStateEnum::STATE_NEW) {
            // If the object is new and has no property, then it has to be empty.
            return;
        } else {
            $this->_dbLoadIfNotLoaded();

            // Let's match the name of the columns to the primary key values
            $fk = $this->tdbmService->_getForeignKeyByName($this->dbTableName, $foreignKeyName);

            $values = [];
            foreach ($fk->getLocalColumns() as $column) {
                $values[] = $this->dbRow[$column];
            }

            $filter = array_combine($this->tdbmService->getPrimaryKeyColumns($fk->getForeignTableName()), $values);

            return $this->tdbmService->findObjectByPk($fk->getForeignTableName(), $filter, [], true);
        }
    }

    /**
     * Returns the name of the table this object comes from.
     *
     * @return string
     */
    public function _getDbTableName()
    {
        return $this->dbTableName;
    }

    /**
     * Method used internally by TDBM. You should not use it directly.
     * This method returns the status of the TDBMObject.
     * This is one of TDBMObjectStateEnum::STATE_NEW, TDBMObjectStateEnum::STATE_NOT_LOADED, TDBMObjectStateEnum::STATE_LOADED, TDBMObjectStateEnum::STATE_DELETED.
     * $status = TDBMObjectStateEnum::STATE_NEW when a new object is created with DBMObject:getNewObject.
     * $status = TDBMObjectStateEnum::STATE_NOT_LOADED when the object has been retrieved with getObject but when no data has been accessed in it yet.
     * $status = TDBMObjectStateEnum::STATE_LOADED when the object is cached in memory.
     *
     * @return string
     */
    public function _getStatus()
    {
        return $this->status;
    }

    /**
     * Override the native php clone function for TDBMObjects.
     */
    public function __clone()
    {
        // Let's load the row (before we lose the ID!)
        $this->_dbLoadIfNotLoaded();

        //Let's set the status to detached
        $this->status = TDBMObjectStateEnum::STATE_DETACHED;

        $this->primaryKeys = [];

        //Now unset the PK from the row
        if ($this->tdbmService) {
            $pk_array = $this->tdbmService->getPrimaryKeyColumns($this->dbTableName);
            foreach ($pk_array as $pk) {
                $this->dbRow[$pk] = null;
            }
        }
    }

    /**
     * Returns raw database row.
     *
     * @return array
     */
    public function _getDbRow()
    {
        // Let's merge $dbRow and $references
        $dbRow = $this->dbRow;

        foreach ($this->references as $foreignKeyName => $reference) {
            // Let's match the name of the columns to the primary key values
            $fk = $this->tdbmService->_getForeignKeyByName($this->dbTableName, $foreignKeyName);
            $refDbRows = $reference->_getDbRows();
            $firstRefDbRow = reset($refDbRows);
            $pkValues = array_values($firstRefDbRow->_getPrimaryKeys());
            $localColumns = $fk->getLocalColumns();

            for ($i = 0, $count = count($localColumns); $i < $count; ++$i) {
                $dbRow[$localColumns[$i]] = $pkValues[$i];
            }
        }

        return $dbRow;
    }

    /**
     * Returns references array.
     *
     * @return AbstractTDBMObject[]
     */
    public function _getReferences()
    {
        return $this->references;
    }

    /**
     * Returns the values of the primary key.
     * This is set when the object is in "loaded" state.
     *
     * @return array
     */
    public function _getPrimaryKeys()
    {
        return $this->primaryKeys;
    }

    /**
     * Sets the values of the primary key.
     * This is set when the object is in "loaded" state.
     *
     * @param array $primaryKeys
     */
    public function _setPrimaryKeys(array $primaryKeys)
    {
        $this->primaryKeys = $primaryKeys;
        foreach ($this->primaryKeys as $column => $value) {
            $this->dbRow[$column] = $value;
        }
    }

    /**
     * Returns the TDBMObject this bean is associated to.
     *
     * @return AbstractTDBMObject
     */
    public function getTDBMObject()
    {
        return $this->object;
    }

    /**
     * Sets the TDBMObject this bean is associated to.
     * Only used when cloning.
     *
     * @param AbstractTDBMObject $object
     */
    public function setTDBMObject(AbstractTDBMObject $object)
    {
        $this->object = $object;
    }
}
