<?php
/*
 Copyright (C) 2006-2014 David Négrier - THE CODING MACHINE

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

namespace Mouf\Database\TDBM;

use Mouf\Database\DBConnection\MySqlConnection;
use Mouf\Utils\Cache\NoCache;

/**
 */
abstract class TDBMAbsctractServiceTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var MySqlConnection $dbConnection
     */
    protected $dbConnection;

    /**
     * @var TDBMService
     */
    protected $tdbmService;

    /**
     * @var TDBMDaoGenerator $tdbmDaoGenerator
     */
    protected $tdbmDaoGenerator;

    protected function setUp() {

        $dbConnectionAdmin = new MySqlConnection();
        $dbConnectionAdmin->host = $GLOBALS['db_host'];
        $dbConnectionAdmin->user = $GLOBALS['db_username'];
        $dbConnectionAdmin->password = $GLOBALS['db_password'];
        $dbConnectionAdmin->port = $GLOBALS['db_port'];

        try {
            $dbConnectionAdmin->dropDatabase($GLOBALS['db_name']);
        } catch (\Exception $e) {
            // We don't care if the database does not exist.
        }
        $dbConnectionAdmin->createDatabase($GLOBALS['db_name']);

        $this->dbConnection = new MySqlConnection();
        $this->dbConnection->host = $GLOBALS['db_host'];
        $this->dbConnection->user = $GLOBALS['db_username'];
        $this->dbConnection->dbname = $GLOBALS['db_name'];
        $this->dbConnection->password = $GLOBALS['db_password'];
        $this->dbConnection->port = $GLOBALS['db_port'];

        $this->dbConnection->executeSqlFile(__DIR__.'/../../../sql/tdbmunittest2.sql');

        $this->tdbmService = new TDBMService();
        $this->tdbmService->dbConnection = $this->dbConnection;
        $this->tdbmService->cacheService = new NoCache();

        $this->tdbmDaoGenerator = new TDBMDaoGenerator($this->dbConnection);
    }
}
