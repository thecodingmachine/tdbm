---
title: The PHP ORM that understands your database schema
hidetitle: true
currentMenu: introduction
---

[![Latest Stable Version](https://poser.pugx.org/thecodingmachine/tdbm/v/stable)](https://packagist.org/packages/thecodingmachine/tdbm)
[![Total Downloads](https://poser.pugx.org/thecodingmachine/tdbm/downloads)](https://packagist.org/packages/thecodingmachine/tdbm)
[![Latest Unstable Version](https://poser.pugx.org/thecodingmachine/tdbm/v/unstable)](https://packagist.org/packages/thecodingmachine/tdbm)
[![License](https://poser.pugx.org/thecodingmachine/tdbm/license)](https://packagist.org/packages/thecodingmachine/tdbm)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/thecodingmachine/tdbm/badges/quality-score.png?b=5.0)](https://scrutinizer-ci.com/g/thecodingmachine/tdbm/?branch=5.0)
[![Build Status](https://travis-ci.org/thecodingmachine/tdbm.svg?branch=5.0)](https://travis-ci.org/thecodingmachine/tdbm)
[![Coverage Status](https://coveralls.io/repos/thecodingmachine/tdbm/badge.svg?branch=5.0&service=github)](https://coveralls.io/github/thecodingmachine/tdbm?branch=5.0)


About TDBM (The DataBase Machine)
=================================

What is it?
-----------

THE DATABASE MACHINE (TDBM) is a PHP library designed to ease your access to your database.

The goal behind TDBM is to make database access as easy as possible. Users should access their objects easily, and store those objects as easily.

Typical workflow
----------------

TDBM is a "database first" ORM. Everything starts from your database. TDBM is very good at understanding your database model and the intent behind it. It will generate PHP objects mapping your model.

![Workflow](https://g.gravizo.com/svg?digraph%20prof%20{"Start%20with%20your%20existing%20database"->"Install%20and%20configure%20TDBM";"Install%20and%20configure%20TDBM"->%20"TDBM%20generates%20PHP%20code%20(DAOs%20and%20beans)";"TDBM%20generates%20PHP%20code%20(DAOs%20and%20beans)"->"You%20write%20your%20queries%20(in%20DAOs)";"You%20write%20your%20queries%20(in%20DAOs)"->"Use%20your%20objects,%20have%20fun!";"Use%20your%20objects,%20have%20fun!"->"Modify%20your%20schema%20in%20SQL";"Modify%20your%20schema%20in%20SQL"->"Regenerate%20PHP%20code%20(DAOs%20and%20beans)";"Regenerate%20PHP%20code%20(DAOs%20and%20beans)"->"Use%20your%20objects,%20have%20fun!";})



Design philosophy
-----------------

TDBM is an opiniated ORM. It will not suit everybody and all needs. Here is what you need to know.

### TDBM starts with your database model and generates PHP files

TODO: schema tout simple database => PHP files

TDBM **understands your database model**. From it, it will generate PHP classes that will help you access your database:
 
 - *DAOs* that are services helping you access a given table
 - and *Beans* that are classes representing a row in your database.

```php
// Daos are used to query the database
$user = $userDao->getById(42);

// Beans have getters and setters for each column
$login = $user->getLogin();
```

Because PHP objects are generated (no magic properties), you get a nice **autocompletion** in your favorite IDE (PHPStorm, Eclipse PDT, Netbeans...).

### TDBM is really good at understanding your database model

TDBM has one of **the most powerful database model analyzer** out there.

 - TDBM relies on your foreign key constraints to find joins between tables.
 - It can automatically detect pivot tables to generate **many to many relationships**. 
 - It can also detect **inheritance** relationships.

### Simplicity first

TDBM is meant to be easy to use and non obtrusive.

**Making simple tasks should be simple.** TDBM does not cover everything you can do with a complete ORM system. 
But it makes as simple as possible those tasks you do 80% of the time. For the remaining 20% (like performance critical requests, and so on), you can use SQL.
For instance, TDBM has a **unique feature that guesses jointures for you**. No need to write joins anymore!

### Based on Doctrine DBAL

TDBM uses the hugely popular Doctrine database abstraction layer for low level database access. It allows compatibility with a very wide range of databases.

### No configuration

There is no configuration needed for TDBM. TDBM needs a DBAL database connection and a Doctrine cache. That's it!

Ready to dive in? Let's get started!

- [Install TDBM](doc/install.md)
- [Access the database, perform queries, inserts and updates](doc/quickstart.md)
- [Add limit and offsets to your queries](doc/limit_offset_resultset.md)
- [Regenerating DAOs and beans](doc/generating_daos.md)
- [Modeling inheritance](doc/modeling_inheritance.md)
- [Improving memory usage](doc/memory_management.md)
- [A quick comparison with Doctrine](doc/comparison_with_doctrine.md)
- [TDBM internals](doc/internals.md)
