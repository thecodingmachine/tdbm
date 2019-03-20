---
title: TDBM Fluid Schema Builder
subTitle: 
currentMenu: integrations_schema_builder
---

TDBM uses your database model to build the beans and DAOs.

The TDBM package itself does not offer any tool to create your data model. You
can use any tool you want (your database's favorite GUI like PhpMyAdmin or a 
migration library like Doctrine Migrations).

However, if you need some help, we provide "TDBM Fluid Schema Builder". It is an additional PHP library
that you can use to create / edit your database model. It can be used to build any 
database model, but has special options to add [database annotations](annotations.md) that can be understood by 
TDBM.

## Installation

```bash
$ composer require thecodingmachine/tdbm-fluid-schema-builder
```

## Usage

The TDBM Fluid Schema Builder is a wrapper around the DBAL `Schema` object that provides a developer
friendly interface.

**Typical usage**
```php
// $schema is a DBAL\Schema instance
$db = new FluidSchema($schema);

$posts = $db->table('posts');

$posts->id() // Let's create a default autoincremented ID column
      ->column('description')->string(50)->null() // Let's create a 'description' column
      ->column('user_id')->references('users');   // Let's create a foreign key.
                                                  // We only specify the table name.
                                                  // FluidSchema infers the column type and the "remote" column.
```

## Supported column types

```php
$table = $db->table('foo');

// Supported types
$table->column('xxxx')->string(50)              // VARCHAR(50)
      ->column('xxxx')->integer()
      ->column('xxxx')->float()
      ->column('xxxx')->text()                  // Long string
      ->column('xxxx')->boolean()
      ->column('xxxx')->smallInt()
      ->column('xxxx')->bigInt()
      ->column('xxxx')->decimal(10, 2)          // DECIMAL(10, 2)
      ->column('xxxx')->guid()
      ->column('xxxx')->binary(255)
      ->column('xxxx')->blob()                  // Long binary
      ->column('xxxx')->date()
      ->column('xxxx')->datetime()
      ->column('xxxx')->datetimeTz()
      ->column('xxxx')->time()
      ->column('xxxx')->dateImmutable()         // From Doctrine DBAL 2.6+
      ->column('xxxx')->datetimeImmutable()     // From Doctrine DBAL 2.6+
      ->column('xxxx')->datetimeTzImmutable()   // From Doctrine DBAL 2.6+
      ->column('xxxx')->timeImmutable()         // From Doctrine DBAL 2.6+
      ->column('xxxx')->dateInterval()          // From Doctrine DBAL 2.6+
      ->column('xxxx')->array()
      ->column('xxxx')->simpleArray()
      ->column('xxxx')->json()                  // From Doctrine DBAL 2.6+
      ->column('xxxx')->jsonArray()             // Deprecated in Doctrine DBAL 2.6+
      ->column('xxxx')->object();               // Serialized PHP object
```

**Shortcut methods:**

```php
// Create an 'id' primary key that is an autoincremented integer
$table->id();

// Don't like autincrements? No problem!
// Create an 'uuid' primary key that is of the DBAL 'guid' type
// The column will be annotated with the @UUID annotation 
$table->uuid('v1'); // UUID supported types can be v1 or v4.

// Create "created_at" and "updated_at" columns
$table->timestamps();
```

**Creating indexes:**

```php
// Directly on a column:
$table->column('login')->string(50)->index();

// Or on the table object (if there are several columns to add to an index):
$table->index(['category1', 'category2']);
```

**Creating unique indexes:**

```php
// Directly on a column:
$table->column('login')->string(50)->unique();

// Or on the table object (if there are several columns to add to the constraint):
$table->unique(['login', 'status']);
```

**Make a column nullable:**

```php
$table->column('description')->string(50)->null();
```

**Set the default value of a column:**

```php
$table->column('enabled')->bool()->default(true);
```

**Create a foreign key**

```php
$table->column('country_id')->references('countries');
```

**Note:** The foreign key will be automatically created on the primary table of the table "countries".
The type of the "country_id" column will be exactly the same as the type of the primary key of the "countries" table.

**Create a jointure table (aka associative table) between 2 tables:**

```php
$db->junctionTable('users', 'roles');

// This will create a 'users_roles' table with 2 foreign keys:
//  - 'user_id' pointing on the PK of 'users'
//  - 'role_id' pointing on the PK of 'roles'
```

**Add a comment to a column:**

```php
$table->column('description')->string(50)->comment('Lorem ipsum');
```

**Declare a primary key:**

```php
$table->column('uuid')->string(36)->primaryKey();

// or

$table->column('uuid')->then()
      ->primaryKey(['uuid']);
```

**Declare an inheritance relationship between 2 tables:**

In SQL, there is no notion of "inheritance" like with PHP objects.
However, [a common way to model inheritance is to write one table for the base class](modeling_inheritance.md) (containing the base columns/properties) and then one table per extended class containing the additional columns/properties.
Each extended table has **a primary key that is also a foreign key pointing to the base table**.

```php
$db->table('contacts')
   ->id()
   ->column('email')->string(50);

$db->table('users')
   ->extends('contacts')
   ->column('password')->string(50);
```

The `extends` method will automatically create a primary key with the same name and same type as the extended table. It will also make sure this primary key is a foreign key pointing to the extended table.

## Customize the beans generation

You can customize the name of a bean (if you want the bean to have a different name than the table)

```php
$posts = $db->table('posts')->customBeanName('Article');
```

## GraphQLite integration

If you are using the [TDBM GraphQLite integration](graphqlite.md), you can also use the TDBM Fluid Schema Builder
to add GraphQLite related annotations:

```php
// The "posts" table will generate a GraphQL type (i.e. the bean will be annotated with the GraphQLite @Type annotation).
$posts = $db->table('posts')->graphqlType();

// You can pass a new 'v1' or 'v4' parameter to uuid().
// This will generate a @UUID TDBM annotation that will help TDBM autogenerate the UUID 
$posts = $db->table('posts')->column('title')->string(50)->graphqlField() // The column is a GraphQL field
            ->fieldName('the_title') // Let's set the name of the field to a different value 
            ->logged() // The user must be logged to view the field
            ->right('CAN_EDIT') // The user must have the 'CAN_EDIT' right to view the field
            ->failWith(null) // If the user is not logged or has no right, let's serve 'null'
            ->endGraphql();

$db->junctionTable('posts', 'users')->graphqlField(); // Expose the many-to-many relationship as a GraphQL field.
```

## Learn more

TDBM Fluid Schema Builder is a wrapper around [DBAL Fluid Schema Builder](https://github.com/thecodingmachine/dbal-fluid-schema-builder).
