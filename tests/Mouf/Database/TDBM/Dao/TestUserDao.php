<?php

namespace Mouf\Database\TDBM\Dao;

use Mouf\Database\TDBM\Test\Dao\Generated\UserBaseDao;
use Mouf\Database\TDBM\UncheckedOrderBy;

/**
 * The UserDao class will maintain the persistence of UserBean class into the users table.
 */
class TestUserDao extends UserBaseDao
{
    /**
     * Returns the list of users by alphabetical order.
     *
     * @return UserBean[]
     */
    public function getUsersByAlphabeticalOrder()
    {
        // The third parameter will be used in the "ORDER BY" clause of the SQL query.
        return $this->find(null, [], 'login ASC');
    }

    /**
     * Returns the list of users whose login starts with $login.
     *
     * @param string $login
     * @param string $mode
     *
     * @return \Mouf\Database\TDBM\ResultIterator|\Mouf\Database\TDBM\Test\Dao\Bean\UserBean[]|\Mouf\Database\TDBM\Test\Dao\ResultArray
     */
    public function getUsersByLoginStartingWith($login, $mode = null)
    {
        return $this->find('login LIKE :login', ['login' => $login.'%'], null, [], $mode);
    }

    /**
     * Returns the user whose login is $login.
     *
     * @param string $login
     *
     * @return \Mouf\Database\TDBM\Test\Dao\Bean\UserBean
     */
    public function getUserByLogin($login)
    {
        return $this->findOne('login = :login', ['login' => $login]);
    }

    public function getUsersByManagerId($managerId)
    {
        return $this->find('contact.manager_id = :id!', ['id' => $managerId]);
    }

    /**
     * Triggers an error because table "contacts" does not exist.
     *
     * @return \Mouf\Database\TDBM\ResultIterator|\Mouf\Database\TDBM\Test\Dao\Bean\UserBean[]|\Mouf\Database\TDBM\Test\Dao\Generated\ResultArray
     */
    public function getUsersWrongTableName()
    {
        return $this->find('contacts.manager_id = 1');
    }

    /**
     * Returns a list of users, sorted by a table on an external column.
     *
     * @return \Mouf\Database\TDBM\ResultIterator|\Mouf\Database\TDBM\Test\Dao\Bean\UserBean[]|\Mouf\Database\TDBM\Test\Dao\Generated\ResultArray
     */
    public function getUsersByCountryName()
    {
        return $this->find(null, [], 'country.label DESC');
    }

    /**
     * A test to sort by function.
     *
     * @return \Mouf\Database\TDBM\ResultIterator|\Mouf\Database\TDBM\Test\Dao\Bean\UserBean[]|\Mouf\Database\TDBM\Test\Dao\Generated\ResultArray
     */
    public function getUsersByReversedCountryName()
    {
        return $this->find(null, [], new UncheckedOrderBy('REVERSE(country.label) ASC'));
    }

    /**
     * A test to check exceptions when providing expressions in ORDER BY clause.
     *
     * @return \Mouf\Database\TDBM\ResultIterator|\Mouf\Database\TDBM\Test\Dao\Bean\UserBean[]|\Mouf\Database\TDBM\Test\Dao\Generated\ResultArray
     */
    public function getUsersByInvalidOrderBy()
    {
        return $this->find(null, [], 'REVERSE(country.label) ASC');
    }
}
