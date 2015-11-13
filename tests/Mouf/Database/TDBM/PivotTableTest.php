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

use Mouf\Utils\Cache\NoCache;
use Mouf\Database\TDBM\Filters\EqualFilter;
use Mouf\Database\TDBM\Filters\OrderByColumn;

/**
 */
class PivotTableTest extends TDBMAbstractServiceTest {

    public function testAddRelationship() {
        $schemaManager = $this->tdbmService->getConnection()->getSchemaManager();
        $pivotTable = new PivotTable('users_roles', $schemaManager->createSchema(), $this->tdbmService->getConnection());

        $userBean = $this->tdbmService->findObjectByPk('users', [ 'id' => 1 ]);
        $roleBean = $this->tdbmService->findObjectByPk('roles', [ 'id' => 1 ]);
        $countryBean = $this->tdbmService->findObjectByPk('country', [ 'id' => 1 ]);

        $pivotTable->addRelationship($userBean, $roleBean, 'loaded');

        $isException = false;
        try {
            $pivotTable->addRelationship($userBean, $countryBean, 'loaded');
        } catch (TDBMException $e) {
            $isException = true;
        }
        $this->assertTrue($isException);

        $pivotTable->addRelationship($roleBean, $userBean, 'deleted');

        $results = $pivotTable->getRelationShips($userBean);
        $this->assertTrue($results->contains($roleBean));

        // FIXME!!!! Impossible to have this working on a NEW object.
        // TODO: remove PivotTable!!!!
        // TODO: find simpler way... for instance by having the array in local in the beans and calling the other bean while saving? (->addRelationship ->_setReverseRelationship?)
        // ->addRelationship($pivotTableName, $remoteBean, $status)
        // Question! Do we save relationship on saving the reverse object? Only from the object we called?
    }
}
