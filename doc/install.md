Installation
============

TDBM offers a number of integration packages to help you get started with your favorite framework. Depending on which framework you are using (or maybe you use no framework?), the installation may vary.

Installation with Mouf
----------------------

Historically, TDBM was the ORM of the Mouf PHP framework. It became a standalone package starting with TDBM 5.0.

To install TDBM with Mouf:

```bash
$ composer require mouf/mouf ~2.0.0
$ composer require mouf/database.tdbm ^5.0
```

Once composer install is done, access the Mouf user interface (http://[your server]/[your app]/vendor/mouf/mouf).
In the user interface, run the graphical installer.

The installer will help you set up the complete environment.

Mouf integration also offers a nice UI in the "Database" menu that helps you regenerate your DAOs and beans.

Next step
---------

Let's now learn how to [access the database](quickstart.md).
