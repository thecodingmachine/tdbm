<?php

namespace TheCodingMachine\TDBM\Dao;

use TheCodingMachine\TDBM\Test\Dao\Bean\ArticleBean;
use TheCodingMachine\TDBM\Test\Dao\Generated\ArticleBaseDao;

/**
 * The UserDao class will maintain the persistence of UserBean class into the users table.
 */
class TestArticleDao extends ArticleBaseDao
{
    /**
     * Used to test a findFromSql with an order by clause on an inherited table.
     *
     * @return ArticleBean[]
     */
    public function getArticlesByUserLogin()
    {
        return $this->findFromSql(
            'article JOIN users ON article.author_id = users.id',
            null,
            [],
            'users.login'
        );
    }
}
