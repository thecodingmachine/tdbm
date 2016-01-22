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

/**
 */
class MapIteratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Mouf\Database\TDBM\TDBMException
     */
    public function testConstructorException1()
    {
        $mapIterator = new MapIterator(new \DateTime(), function ($item) {
            return $item;
        });
    }

    /**
     * @expectedException \Mouf\Database\TDBM\TDBMException
     */
    public function testConstructorException2()
    {
        $mapIterator = new MapIterator(array(1, 2, 3), function () {
            return $item;
        });
    }
}
