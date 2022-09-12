<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Dao;

use TheCodingMachine\TDBM\Test\Dao\Bean\AlbumBean;
use TheCodingMachine\TDBM\Test\Dao\Generated\AlbumBaseDao;

/**
 * The AlbumDao class will maintain the persistence of UserBean class into the users table.
 */
class TestAlbumDao extends AlbumBaseDao
{
    /**
     * @return \TheCodingMachine\TDBM\ResultIterator|AlbumBean[]
     */
    public function findAllFromRawSql()
    {
        return $this->findFromRawSql('SELECT DISTINCT albums.* FROM albums');
    }

    /**
     * @return \TheCodingMachine\TDBM\ResultIterator|AlbumBean[]
     */
    public function findAllFromRawSqlWithCount()
    {
        return $this->findFromRawSql(
            'SELECT DISTINCT albums.* FROM albums',
            [],
            'SELECT COUNT(DISTINCT albums.id) FROM albums'
        );
    }
}
