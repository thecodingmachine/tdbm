About TDBM (The DataBase Machine)
=================================

Design philosophy
-----------------

THE DATABASE MACHINE (TDBM) is a PHP library designed to ease your access to your database.

The goal behind TDBM is to make database access as easy as possible. Users should access their objects easily, and store those objects as easily.

Design choices:

- _Simplicity first._ TDBM is not a library you would use to get high performance requests. It is small, easy to use, and non obstrusive. We tried to optimize it as much as possible, but each time we had a trade-off between performance and simplicity, we chose simplicity.
- _Making simple tasks should be simple._ TDBM does not cover everything you can do with a complete ORM system. But it makes as simple as possible those tasks you do 80% of the time. For the remaining 20% (like performance critical requests, and so on), you can use SQL.
- _No configuration._ There is no configuration needed for TDBM. You provide TDBM the name of your database, the user and password and you start using it.
- _TDBM relies on the model of your database._ TDBM relies on your database model to find joins between tables. It relies in fact on constraints between tables. TDBM finds these constraints itself in the database by querying the db system.


Ready to dive in? [Let's get started!](doc/quickstart.md)