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
use Mouf\Database\TDBM\Filters\EqualFilter;
use Mouf\Database\TDBM\Filters\OrderByColumn;

// Require needed if we run this class directly
if (file_exists(__DIR__.'/../../../../../../autoload.php')) {
    require_once __DIR__.'/../../../../../../autoload.php';
} else {
    require_once __DIR__.'/../../../../vendor/autoload.php';
}

/**
 */
class TDBMAbsctractServiceTest extends \PHPUnit_Framework_TestCase {

    protected $dbConnection;
    protected $tdbmService;

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
    }

    static function main() {
        $suite = new \PHPUnit_Framework_TestSuite( __CLASS__);
        \PHPUnit_TextUI_TestRunner::run( $suite);
    }

    /**
     * Without this only test for the abscract class, PhP Unit will raise a warning:
     * No tests found in class "Mouf\Database\TDBM\TDBMAbsctractServiceTest"
     */
    public function testFake() {
        // do nothing
    }
}

if (!defined('PHPUnit_MAIN_METHOD')) {
	require_once __DIR__.'/../../../../vendor/autoload.php';
    TDBMAbsctractServiceTest::main();
}
?>