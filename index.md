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

- _TDBM relies on the model of your database._ It has one of **the most powerful database model analyzer** that helps it 
  map tables to objects. TDBM understands the meaning of your database model and the intent of it.
  TDBM relies on your database model to find joins between tables.  It relies in fact on foreign key constraints.
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

Ready to dive in? [Let's get started!](doc/quickstart.md)

A quick comparison with Doctrine ORM
------------------------------------

TDBM is built by [TheCodingMachine](http://www.thecodingmachine.com), a parisian web-shop. The first version was started 
in 2006 (!) and we use TDBM daily in our work since then. So in case you wonder, yes, it is production ready.

There are other ORMs out there, the biggest one being Doctrine ORM. Actually, TDBM relies on Doctrine DBAL (the 
database abstraction layer), but it does not use the higher level modules of Doctrine ORM. The TheCodingMachine developers 
use both TDBM and Doctrine ORM on their day to day work. Here is a quick comparison of both products and what we do 
to choose which ORM we are going to use on a per-project basis.

### Model based vs object based ORM

In TDBM, you start writing your database model, then TDBM generates beans (= entities) and DAOs (= repositories) for you.
In Doctrine, instead, you start writing your classes and the database model is created from the PHP code.

TDBM approach is best for simpler apps with a relatively simple database model. It is obviously simpler to use when 
you are migrating an application with an existing database to TDBM.

Doctrine approach is great for more complex applications. It is the way to go if you want to do DDD (domain driven
design), since the objects are relatively independent from the ORM (unlike in TDBM or all other "active records" ORM 
where your objects have to extend from a class provided by the framework).

### Simplicity vs completion

TDBM comes with a number of tools to help you write queries very easily. In particular, it can guess the jointures 
between tables, so you don't have to care about those. This works very well with simple to moderately complex data 
models, and you will find that you can be extremely efficient writing queries with TDBM. However, TDBM deals poorly 
with database models that have "loops" in their model (like a hierarchical relationship where a foreign key 
in one table points on the same table). For those use cases, you should go back to plain SQL.

Doctrine ORM on the other hand comes with a full query language (DQL). You can express almost any SQL queries in DQL, 
so it is clearly more powerful. Of course, you have to learn DQL first, so less simple...

