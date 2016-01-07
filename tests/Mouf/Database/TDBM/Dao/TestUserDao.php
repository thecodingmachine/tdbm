<?php

namespace Mouf\Database\TDBM\Dao;

use Mouf\Database\TDBM\Test\Dao\UserBaseDao;

/**
 * The UserDao class will maintain the persistence of UserBean class into the users table.
 */
class TestUserDao extends UserBaseDao
{
    /**
     * Returns the list of users by alphabetical order
     *
     * @return UserBean[]
     */
    public function getUsersByAlphabeticalOrder() {
        // The third parameter will be used in the "ORDER BY" clause of the SQL query.
        return $this->getListByFilter(null, [], 'login ASC');
    }
}
