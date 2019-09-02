<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Dao;

use TheCodingMachine\TDBM\ResultIterator;
use TheCodingMachine\TDBM\TDBMService;
use TheCodingMachine\TDBM\Test\Dao\Bean\ArticleBean;
use TheCodingMachine\TDBM\Test\Dao\Generated\ArticleBaseDao;

/**
 * The UserDao class will maintain the persistence of UserBean class into the users table.
 */
class TestArticleSubQueryDao extends ArticleBaseDao
{
    /**
     * @var TestUserDao
     */
    private $userDao;

    public function __construct(TDBMService $tdbmService, TestUserDao $userDao)
    {
        parent::__construct($tdbmService);
        $this->userDao = $userDao;
    }

    /**
     * Used to test a findFromSql with an order by clause on an inherited table.
     *
     * @return ResultIterator&ArticleBean[]
     */
    public function getArticlesByUserLoginStartingWith(string $login): ResultIterator
    {
        /*return $this->find(
            'author_id IN (:authorIds)',
            [ 'authorIds' => $this->userDao->getUsersByLoginStartingWith($login) ],
            'users.login'
        );*/
        return $this->find(
            $this->userDao->getUsersByLoginStartingWith($login),
            [],
            'users.login'
        );
    }
}
