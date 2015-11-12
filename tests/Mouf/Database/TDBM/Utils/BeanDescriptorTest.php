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

namespace Mouf\Database\TDBM\Utils;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Schema\Schema;
use Mouf\Database\DBConnection\MySqlConnection;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use Mouf\Database\TDBM\TDBMAbstractServiceTest;
use Mouf\Database\TDBM\Test\Dao\Bean\UserBean;
use Mouf\Database\TDBM\Test\Dao\ContactDao;
use Mouf\Database\TDBM\Test\Dao\CountryDao;
use Mouf\Database\TDBM\Test\Dao\UserDao;
use Mouf\Database\TDBM\Utils\TDBMDaoGenerator;
use Mouf\Utils\Cache\NoCache;


/**
 */
class BeanDescriptorTest extends TDBMAbstractServiceTest {

    /**
     * @var Schema
     */
    protected $schema;
    /**
     * @var SchemaAnalyzer
     */
    protected $schemaAnalyzer;


    protected function setUp() {
        parent::setUp();
        $schemaManager = $this->tdbmService->getConnection()->getSchemaManager();
        $this->schemaAnalyzer = new SchemaAnalyzer($schemaManager);
        $this->schema = $schemaManager->createSchema();
    }

	public function testConstructor() {
        $usersTable = $this->schema->getTable("users");
        $beanDescriptor = new BeanDescriptor($usersTable, $this->schemaAnalyzer, $this->schema);
        $propertyDescriptors = $beanDescriptor->getBeanPropertyDescriptors();
        $firstElem = reset($propertyDescriptors);
        $idProperty = $propertyDescriptors['id'];
        $this->assertEquals($firstElem, $idProperty);
        $this->assertEquals("person", $idProperty->getTable()->getName());
        $this->assertInstanceOf("Mouf\\Database\\TDBM\\Utils\\ScalarBeanPropertyDescriptor", $idProperty);
        $countryProperty = $propertyDescriptors['country'];
        $this->assertInstanceOf("Mouf\\Database\\TDBM\\Utils\\ObjectBeanPropertyDescriptor", $countryProperty);
        $nameProperty = $propertyDescriptors['name'];
        $this->assertInstanceOf("Mouf\\Database\\TDBM\\Utils\\ScalarBeanPropertyDescriptor", $nameProperty);
    }

    public function testGetConstructorProperties() {
        $usersTable = $this->schema->getTable("users");
        $beanDescriptor = new BeanDescriptor($usersTable, $this->schemaAnalyzer, $this->schema);
        $constructorPropertyDescriptors = $beanDescriptor->getConstructorProperties();
        $this->assertArrayHasKey("name", $constructorPropertyDescriptors);
        // password is nullable
        $this->assertArrayNotHasKey("password", $constructorPropertyDescriptors);
        // id is autoincremented
        $this->assertArrayNotHasKey("id", $constructorPropertyDescriptors);
    }

    public function testGetIncomingForeignKeys() {
        $usersTable = $this->schema->getTable("users");
        $beanDescriptor = new BeanDescriptor($usersTable, $this->schemaAnalyzer, $this->schema);
        $fks = $beanDescriptor->getIncomingForeignKeys();
        $this->assertCount(0, $fks);
    }

    public function testGetIncomingForeignKeys2() {
        $contactsTable = $this->schema->getTable("contact");
        $beanDescriptor = new BeanDescriptor($contactsTable, $this->schemaAnalyzer, $this->schema);
        $fks = $beanDescriptor->getIncomingForeignKeys();
        $this->assertCount(0, $fks);
    }

    public function testGetIncomingForeignKeys3() {
        $table = $this->schema->getTable("country");
        $beanDescriptor = new BeanDescriptor($table, $this->schemaAnalyzer, $this->schema);
        $fks = $beanDescriptor->getIncomingForeignKeys();
        $this->assertCount(1, $fks);
        $this->assertEquals('users', $fks[0]->getLocalTableName());
    }

    /*public function testGeneratePhpCode() {
        $usersTable = $this->schema->getTable("users");
        $beanDescriptor = new BeanDescriptor($usersTable, $this->schemaAnalyzer, $this->schema);
        $phpCode = $beanDescriptor->generatePhpCode("MyNamespace\\");

        echo $phpCode;
    }*/
}
