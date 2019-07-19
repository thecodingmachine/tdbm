<?php
declare(strict_types=1);

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

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\Common\Cache\VoidCache;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Mouf\Database\SchemaAnalyzer\SchemaAnalyzer;
use TheCodingMachine\TDBM\TDBMAbstractServiceTest;
use TheCodingMachine\TDBM\TDBMException;
use TheCodingMachine\TDBM\TDBMSchemaAnalyzer;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;

class BeanDescriptorTest extends TDBMAbstractServiceTest
{
    /**
     * @var Schema
     */
    protected $schema;
    /**
     * @var SchemaAnalyzer
     */
    protected $schemaAnalyzer;

    /**
     * @var TDBMSchemaAnalyzer
     */
    protected $tdbmSchemaAnalyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $schemaManager = $this->tdbmService->getConnection()->getSchemaManager();
        $this->schemaAnalyzer = new SchemaAnalyzer($schemaManager);
        $this->schema = $schemaManager->createSchema();
        $this->tdbmSchemaAnalyzer = new TDBMSchemaAnalyzer($this->tdbmService->getConnection(), new VoidCache(), $this->schemaAnalyzer);
    }

    public function testConstructor(): void
    {
        $usersTable = $this->schema->getTable('users');
        $beanDescriptor = new BeanDescriptor($usersTable, 'Tdbm\\Test\\Beans', 'Tdbm\\Test\\Beans\\Generated', 'Tdbm\\Test\\Daos', 'Tdbm\\Test\\Daos\\Generated', $this->schemaAnalyzer, $this->schema, $this->tdbmSchemaAnalyzer, $this->getNamingStrategy(), AnnotationParser::buildWithDefaultAnnotations([]), new BaseCodeGeneratorListener(), $this->getConfiguration());
        $propertyDescriptors = $beanDescriptor->getBeanPropertyDescriptors();
        $firstElem = reset($propertyDescriptors);
        $idProperty = $propertyDescriptors['$id'];
        $this->assertEquals($firstElem, $idProperty);
        $this->assertEquals('person', $idProperty->getTable()->getName());
        $this->assertInstanceOf('TheCodingMachine\\TDBM\\Utils\\ScalarBeanPropertyDescriptor', $idProperty);
        $countryProperty = $propertyDescriptors['$country'];
        $this->assertInstanceOf('TheCodingMachine\\TDBM\\Utils\\ObjectBeanPropertyDescriptor', $countryProperty);
        $nameProperty = $propertyDescriptors['$name'];
        $this->assertInstanceOf('TheCodingMachine\\TDBM\\Utils\\ScalarBeanPropertyDescriptor', $nameProperty);
    }

    public function testGetConstructorProperties(): void
    {
        $usersTable = $this->schema->getTable('users');
        $beanDescriptor = new BeanDescriptor($usersTable, 'Tdbm\\Test\\Beans', 'Tdbm\\Test\\Beans\\Generated', 'Tdbm\\Test\\Daos', 'Tdbm\\Test\\Daos\\Generated', $this->schemaAnalyzer, $this->schema, $this->tdbmSchemaAnalyzer, $this->getNamingStrategy(), AnnotationParser::buildWithDefaultAnnotations([]), new BaseCodeGeneratorListener(), $this->getConfiguration());
        $constructorPropertyDescriptors = $beanDescriptor->getConstructorProperties();
        $this->assertArrayHasKey('$name', $constructorPropertyDescriptors);
        // password is nullable
        $this->assertArrayNotHasKey('$password', $constructorPropertyDescriptors);
        // id is autoincremented
        $this->assertArrayNotHasKey('$id', $constructorPropertyDescriptors);
    }

    public function testGetTable(): void
    {
        $usersTable = $this->schema->getTable('users');
        $beanDescriptor = new BeanDescriptor($usersTable, 'Tdbm\\Test\\Beans', 'Tdbm\\Test\\Beans\\Generated', 'Tdbm\\Test\\Daos', 'Tdbm\\Test\\Daos\\Generated', $this->schemaAnalyzer, $this->schema, $this->tdbmSchemaAnalyzer, $this->getNamingStrategy(), AnnotationParser::buildWithDefaultAnnotations([]), new BaseCodeGeneratorListener(), $this->getConfiguration());
        $this->assertSame($usersTable, $beanDescriptor->getTable());
    }

    public function testTableWithNoPrimaryKey(): void
    {
        $table = new Table('no_primary_key');
        $this->expectException(TDBMException::class);
        $this->expectExceptionMessage('Table "no_primary_key" does not have any primary key');
        new BeanDescriptor($table, 'Foo\\Bar', 'Foo\\Generated\\Bar', 'Tdbm\\Test\\Daos', 'Tdbm\\Test\\Daos\\Generated', $this->schemaAnalyzer, $this->schema, $this->tdbmSchemaAnalyzer, $this->getNamingStrategy(), AnnotationParser::buildWithDefaultAnnotations([]), new BaseCodeGeneratorListener(), $this->getConfiguration());
    }

    public function testTableWithLazyLoadingColumn(): void
    {
        $table = $this->schema->createTable('lazy_loading');
        $table->addColumn('lazyLoading', Type::BOOLEAN);
        $table->setPrimaryKey(['lazyLoading']);
        $sqlStmts = $this->schema->getMigrateFromSql($this->getConnection()->getSchemaManager()->createSchema(), $this->getConnection()->getDatabasePlatform());

        foreach ($sqlStmts as $sqlStmt) {
            $this->getConnection()->exec($sqlStmt);
        }

        $this->expectException(TDBMException::class);
        $this->expectExceptionMessage('Primary Column name `lazyLoading` is not allowed.');
        $beanDescriptor = new BeanDescriptor($table, 'Foo\\Bar', 'Foo\\Generated\\Bar', 'Tdbm\\Test\\Daos', 'Tdbm\\Test\\Daos\\Generated', $this->schemaAnalyzer, $this->schema, $this->tdbmSchemaAnalyzer, $this->getNamingStrategy(), AnnotationParser::buildWithDefaultAnnotations([]), new BaseCodeGeneratorListener(), $this->getConfiguration());
        $beanDescriptor->generateDaoPhpCode();
    }

    /*public function testGeneratePhpCode() {
        $usersTable = $this->schema->getTable("users");
        $beanDescriptor = new BeanDescriptor($usersTable, $this->schemaAnalyzer, $this->schema);
        $phpCode = $beanDescriptor->generatePhpCode("MyNamespace\\");

        echo $phpCode;
    }*/
}
