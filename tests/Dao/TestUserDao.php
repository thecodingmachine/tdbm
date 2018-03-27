<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Dao;

use TheCodingMachine\TDBM\Test\Dao\Bean\CountryBean;
use TheCodingMachine\TDBM\Test\Dao\Bean\UserBean;
use TheCodingMachine\TDBM\Test\Dao\Generated\UserBaseDao;
use TheCodingMachine\TDBM\UncheckedOrderBy;

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
     * Returns the list of users by alphabetical order.
     *
     * @return UserBean[]
     */
    public function getUsersFromSqlByAlphabeticalOrder()
    {
        // The third parameter will be used in the "ORDER BY" clause of the SQL query.
        return $this->findFromSql('users', null, [], 'users.login ASC');
    }

    /**
     * Returns the list of users by alphabetical order.
     *
     * @return UserBean[]
     */
    public function getUsersByCountryOrder()
    {
        // The third parameter will be used in the "ORDER BY" clause of the SQL query.
        return $this->find(null, [], 'country.label ASC', ['country']);
    }
    /**
     * Returns the list of users by alphabetical order.
     *
     * @return UserBean[]
     */
    public function getUsersFromSqlByCountryOrder()
    {
        // The third parameter will be used in the "ORDER BY" clause of the SQL query.
        return $this->findFromSql('users JOIN country ON country.id = users.country_id', null, [], 'country.label ASC');
    }

    /**
     * Returns the list of users whose login starts with $login.
     *
     * @param string $login
     * @param string $mode
     *
     * @return \TheCodingMachine\TDBM\ResultIterator|UserBean[]
     */
    public function getUsersByLoginStartingWith($login = null, $mode = null)
    {
        return $this->find('login LIKE :login', ['login' => $login.'%'], null, [], $mode);
    }

    /**
     * Returns the user whose login is $login.
     *
     * @param string $login
     *
     * @return UserBean
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
     * @return \TheCodingMachine\TDBM\ResultIterator|UserBean[]
     */
    public function getUsersWrongTableName()
    {
        return $this->find('contacts.manager_id = 1');
    }

    /**
     * Returns a list of users, sorted by a table on an external column.
     *
     * @return \TheCodingMachine\TDBM\ResultIterator|UserBean[]
     */
    public function getUsersByCountryName()
    {
        return $this->find(null, [], 'country.label DESC');
    }

    /**
     * A test to sort by function.
     *
     * @return \TheCodingMachine\TDBM\ResultIterator|UserBean[]
     */
    public function getUsersByReversedCountryName()
    {
        return $this->find(null, [], new UncheckedOrderBy('REVERSE(country.label) ASC'));
    }

    /**
     * A test to check exceptions when providing expressions in ORDER BY clause.
     *
     * @return \TheCodingMachine\TDBM\ResultIterator|UserBean[]
     */
    public function getUsersByInvalidOrderBy()
    {
        return $this->find(null, [], 'REVERSE(country.label) ASC');
    }

    /**
     * @param CountryBean $country
     * @param string[] $names
     * @return \TheCodingMachine\TDBM\ResultIterator|UserBean[]
     */
    public function getUsersByComplexFilterBag(CountryBean $country, array $names)
    {
        $filterBag = [
            'person.name' => $names
        ];
        $filterBag[] = $country;
        return $this->find($filterBag);
    }
}
