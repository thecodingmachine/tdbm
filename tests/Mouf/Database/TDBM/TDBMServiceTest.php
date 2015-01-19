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
class TDBMServiceTest extends \PHPUnit_Framework_TestCase {
	
	protected $dbConnection;
	protected $tdbmService;
	
	protected function setUp()
	{
		$dbConnectionAdmin = new MySqlConnection();
		$dbConnectionAdmin->host = 'localhost';
		$dbConnectionAdmin->user = 'root';
		
		try {
			$dbConnectionAdmin->dropDatabase('tdbm_testcase');
		} catch (\Exception $e) {
			// We don't care if the database does not exist.
		}
		$dbConnectionAdmin->createDatabase('tdbm_testcase');

		$this->dbConnection = new MySqlConnection();
		$this->dbConnection->host = 'localhost';
		$this->dbConnection->user = 'root';
		$this->dbConnection->dbname = 'tdbm_testcase';
		
		$this->dbConnection->executeSqlFile(__DIR__.'/../../../sql/tdbmunittest2.sql');
		
		$this->tdbmService = new TDBMService();
		$this->tdbmService->dbConnection = $this->dbConnection;
		$this->tdbmService->cacheService = new NoCache();
	}
	
	public function testOneWayAndTheOpposite() {
		$this->tdbmService->getObjects('utilisateur_entite', new EqualFilter('entites', 'appellation', 'foo'));
		$this->tdbmService->getObjects('entites', new EqualFilter('utilisateur_entite', 'id_utilisateur', '1'));
	}

	public function testOneWayAndTheOpposite2() {
		$this->tdbmService->getObjects('utilisateur_entite', new EqualFilter('departements', 'id', '1'));
		$this->tdbmService->getObjects('departements', new EqualFilter('utilisateur_entite', 'id_utilisateur', '1'));
	}
	
	public function testOneWayAndTheOpposite3() {
		$this->tdbmService->getObjects('utilisateur_entite', 
				[
				new EqualFilter('entites', 'appellation', 1),
				]
		);
		$this->tdbmService->getObjects('entites', [
					new EqualFilter('departements', 'id', 1),
					new EqualFilter('utilisateur_entite', 'id_utilisateur', '1'),
				]
		);
	}
	
	public function testOneWayAndTheOpposite4() {
		$this->tdbmService->getObjects('utilisateur_entite', null,
				[
				new OrderByColumn('entites', 'appellation', 'ASC'),
				]
		);
		$this->tdbmService->getObjects('entites', new EqualFilter('utilisateur_entite', 'id_utilisateur', '1'),
				[
				new OrderByColumn('departements', 'id', 'ASC')
				]
		);
	}
	
	public function testTDBMObjectArrayMultipleForeach() {
		$results = $this->tdbmService->getObjects('departements');
		$this->assertTrue(is_array($results));
		$count = 0;
		foreach ($results as $result) {
			$count++;
		}
		$this->assertEquals(95, $count);

		$count = 0;
		foreach ($results as $result) {
			$count++;
		}
		$this->assertEquals(95, $count);
		
	}
	
	public function testTDBMObjectCursorMode() {
		$this->tdbmService->setFetchMode(TDBMService::MODE_CURSOR);
		$results = $this->tdbmService->getObjects('departements');

		$count = 0;
		foreach ($results as $result) {
			$count++;
		}
		$this->assertEquals(95, $count);
	}

	
	public function testTDBMObjectArrayCount() {
		$results = $this->tdbmService->getObjects('departements');
		$this->assertEquals(95, count($results));
		$this->assertEquals(95, count($results));
	
	}
	
	
	public function testTDBMObjectArrayAccessByKey() {
		$results = $this->tdbmService->getObjects('departements');
		
		$this->assertEquals("Alpes Maritimes", $results[5]->nom);
	}
	
	public function testTDBMObjectArrayJsonEncode() {
		$this->tdbmService->setFetchMode(TDBMService::MODE_COMPATIBLE_ARRAY);
		$jsonEncoded = json_encode($this->tdbmService->getObjects('departements'));
		$count = count(json_decode($jsonEncoded));
		
		$this->assertEquals(95, $count);
	}

	public function testInnerJsonEncode() {
		$this->tdbmService->setFetchMode(TDBMService::MODE_COMPATIBLE_ARRAY);
		$departements = $this->tdbmService->getObjects('departements');
		$jsonEncoded = json_encode(['departements'=>$departements]);
		$count = count(json_decode($jsonEncoded, true)['departements']);
		
		$this->assertEquals(95, $count);
	}

	
	public function testCursorJsonEncode() {
		// COMMENTING THE WHOLE SCRIPT.
		// If we are in CURSOR mode, there is probably no point in json_encoding the result.
		/*$this->tdbmService->setFetchMode(TDBMService::MODE_CURSOR);
		$results = $this->tdbmService->getObjects('departements');
		$jsonEncoded = json_encode($results);
		$count = count(json_decode($jsonEncoded, true));
		
		$this->assertEquals(95, $count);
		*/
	}

    public function testTDBMObjectArrayCountAfterForeach() {
        $results = $this->tdbmService->getObjects('departements');
        foreach ($results as $result) {
            // Do nothing
        }
        $this->assertEquals(95, count($results));
    }
	
	static function main() {
	
		$suite = new \PHPUnit_Framework_TestSuite( __CLASS__);
		\PHPUnit_TextUI_TestRunner::run( $suite);
	}
}


if (!defined('PHPUnit_MAIN_METHOD')) {
	require_once __DIR__.'/../../../../vendor/autoload.php';
	TDBMServiceTest::main();
}

?>