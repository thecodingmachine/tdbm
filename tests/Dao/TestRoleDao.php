<?php

namespace TheCodingMachine\TDBM\Dao;

use TheCodingMachine\TDBM\Test\Dao\Bean\RoleBean;
use TheCodingMachine\TDBM\Test\Dao\Generated\RoleBaseDao;

/**
 * The UserDao class will maintain the persistence of UserBean class into the users table.
 */
class TestRoleDao extends RoleBaseDao
{
    /**
     * Returns the list of roles join rights where right label = CAN_SING.
     *
     * @return RoleBean[]
     */
    public function getRolesByRightCanSing($orderBy = null)
    {
        return $this->findFromSql('roles JOIN roles_rights ON roles.id = roles_rights.role_id JOIN rights ON rights.label = roles_rights.right_label',
            'rights.label = :right', array('right' => 'CAN_SING'), $orderBy);
    }
    /**
     * Returns the list of roles join rights where right label = CAN_SING.
     *
     * @return RoleBean[]
     */
    public function getRolesByRight($orderBy = null)
    {
        return $this->findFromSql('roles JOIN roles_rights ON roles.id = roles_rights.role_id JOIN rights ON rights.label = roles_rights.right_label',
            null, [], $orderBy);
    }

    /**
     * Returns the role join rights where right label = CAN_SING and role name = Singers.
     *
     * @return RoleBean
     */
    public function getRoleByRightCanSingAndNameSinger()
    {
        return $this->findOneFromSql('roles JOIN roles_rights ON roles.id = roles_rights.role_id JOIN rights ON rights.label = roles_rights.right_label',
            'rights.label = :right AND name = :name', array('right' => 'CAN_SING', 'name' => 'Singers'));
    }
}
