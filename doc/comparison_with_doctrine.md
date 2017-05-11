A quick comparison with Doctrine ORM
====================================

TDBM is built by [TheCodingMachine](http://www.thecodingmachine.com), a parisian web-shop. The first version was started 
in 2006 (!) and we use TDBM on a daily basis in our work since then. So in case you wonder, yes, it is production ready.

There are other ORMs out there, the biggest one being Doctrine ORM. Actually, TDBM relies on Doctrine DBAL (the 
database abstraction layer), but it does not use the higher level modules of Doctrine ORM. The TheCodingMachine developers 
use both TDBM and Doctrine ORM on their day to day work. Here is a quick comparison of both products and what we do 
to choose which ORM we are going to use on a per-project basis.

## Model based vs object based ORM

In TDBM, you start writing your database model, then TDBM generates beans (= entities) and DAOs (= repositories) for you.
In Doctrine, instead, you start writing your classes and the database model is created from the PHP code (to be 100% 
accurate, Doctrine relies on a mapping between your objects and your database, but most of the users out there use the
Doctrine annotations for writing this "mapping", hence the PHP code first approach).

TDBM approach is best for simpler apps with a relatively simple database model. It is obviously simpler to use when 
you are migrating an application with an existing database to TDBM.

Doctrine approach is great for more complex applications. It is the way to go if you want to do DDD (domain driven
design), since the objects are relatively independent from the ORM (unlike in TDBM or all other "active records" ORM 
where your objects have to extend from a class provided by the framework).

## Simplicity vs completion

TDBM comes with a number of tools to help you write queries very easily. In particular, it can guess the jointures 
between tables, so you don't have to care about those. This works very well with simple to moderately complex data 
models, and you will find that you can be extremely efficient writing queries with TDBM. However, TDBM deals poorly 
with database models that have "loops" in their model (like a hierarchical relationship where a foreign key 
in one table points on the same table). For those use cases, you should go back to plain SQL.

Doctrine ORM on the other hand comes with a full query language (DQL). You can express almost any SQL queries in DQL, 
so it is clearly more powerful. Of course, you have to learn DQL first, so it is less simple...
