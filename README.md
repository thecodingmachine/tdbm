[![Latest Stable Version](https://poser.pugx.org/mouf/database.tdbm/v/stable)](https://packagist.org/packages/mouf/database.tdbm)
[![Total Downloads](https://poser.pugx.org/mouf/database.tdbm/downloads)](https://packagist.org/packages/mouf/database.tdbm)
[![Latest Unstable Version](https://poser.pugx.org/mouf/database.tdbm/v/unstable)](https://packagist.org/packages/mouf/database.tdbm)
[![License](https://poser.pugx.org/mouf/database.tdbm/license)](https://packagist.org/packages/mouf/database.tdbm)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/thecodingmachine/database.tdbm/badges/quality-score.png?b=4.0)](https://scrutinizer-ci.com/g/thecodingmachine/database.tdbm/?branch=4.0)
[![Build Status](https://travis-ci.org/thecodingmachine/database.tdbm.svg?branch=4.0)](https://travis-ci.org/thecodingmachine/database.tdbm)
[![Coverage Status](https://coveralls.io/repos/thecodingmachine/database.tdbm/badge.svg?branch=4.0&service=github)](https://coveralls.io/github/thecodingmachine/database.tdbm?branch=4.0)


About TDBM (The DataBase Machine)
=================================

What is it?
-----------

THE DATABASE MACHINE (TDBM) is a PHP library designed to ease your access to your database.

The goal behind TDBM is to make database access as easy as possible. Users should access their objects easily, and store those objects as easily.


Design philosophy
-----------------

Design choices:

- _TDBM starts with your database model and generates PHP files._ TDBM **understands your database model**. From it,
  it will generate PHP classes that will help you access your database. It will generate 2 kind of classes: *DAOs*
  that are services helping you access a given table, and *Beans* that are classes representing a row in your database.
  Because PHP objects are generated, it means you get a nice **autocompletion** in your favorite IDE. 
- _TDBM is really good at understanding the meaning and the intent behind your database model._
  TDBM has one of **the most powerful database model analyzer** that helps it 
  map tables to objects.
  TDBM relies on your foreign key constraints to find joins between tables.
  TDBM finds these constraints itself in the database by querying the 
  DB system. It can automatically detect pivot table to generate **many to many relationships**. It can also 
  detect **inheritance** relationships.
- _Simplicity first._ TDBM is meant to be easy to use and non obtrusive. 
- _Making simple tasks should be simple._ TDBM does not cover everything you can do with a complete ORM system. 
  But it makes as simple as possible those tasks you do 80% of the time. For the remaining 20% (like performance critical requests, and so on), you can use SQL.
  For instance, TDBM has a **unique feature that guesses jointures for you**. No need to write joins anymore! 
- _No configuration._ There is no configuration needed for TDBM. You provide TDBM the name of your database, the user and password and you start using it.
- _Based on Doctrine DBAL._ TDBM uses the Doctrine database abstraction layer for low level database access. It allows
  compatibility with a very wide range of databases.

Ready to dive in? Let's get started!

- [Install TDBM](doc/install.md)
- [Access the database, perform queries, inserts and updates](doc/quickstart.md)
- [Add limit and offsets to your queries](doc/limit_offset_resultset.md)
- [Regenerating DAOs and beans](doc/generating_daos.md)
- [Modeling inheritance](doc/modeling_inheritance.md)
- [Improving memory usage](doc/memory_management.md)
- [A quick comparison with Doctrine](doc/comparison_with_doctrine.md)
- [TDBM internals](doc/internals.md)
