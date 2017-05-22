---
title: Installing TDBM in Silex
subTitle: 
currentMenu: install_silex
---

## Installation

To install TDBM in Silex 2:

```bash
composer require thecodingmachine/tdbm-silex ^5.0
```

<div class="alert alert-warning">
In this document, we will assume you started your Silex application using the <a href="https://github.com/silexphp/Silex-Skeleton">silex-skeleton</a> package.
If you did not use this package to start your project, you might need to adjust the paths of files in this documentation.
</div>

### Registering the service provider

Then edit your `src/app.php` file and register these 3 service providers:

```php
// Let's register the database connection
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'   => 'pdo_mysql',
        'dbname'   => 'my_db',
        'host'     => 'localhost',
        'user'     => 'my_user',
        'password' => 'my_password'
    ),
));

// Let's register the Doctrine cache
$app->register(new \CHH\Silex\CacheServiceProvider, [
    'cache.options' => [
        'default' => [
            // Customize this to another cache driver if you don't have APCu installed
            'driver' => \Doctrine\Common\Cache\ApcuCache::class,
        ],
    ],
]);

// Finally, let's register TDBM
$app->register(new \TheCodingMachine\TDBM\Silex\Providers\TdbmServiceProvider(), [
    'tdbm.daoNamespace' => 'App\Daos',
    'tdbm.beanNamespace' => 'App\Beans'
]);
```

### Registering the console command

Now, we need to register a command in the Silex console.

Assuming you started from the <a href="https://github.com/silexphp/Silex-Skeleton">silex-skeleton</a> package, you should edit the `src/console.php` file.

```php
$console->add(new \TheCodingMachine\TDBM\Commands\GenerateCommand($app['tdbm.configuration']));
```

Note: if you do not have a console (because you started from a blank Silex project), you can use the [KnpLabs' Console Service Provider](https://github.com/KnpLabs/ConsoleServiceProvider) to add support for the Symfony console and then, register the `TheCodingMachine\TDBM\Commands\GenerateCommand` command provided by TDBM.

## Generating beans and DAOs

When installation is done, you need to generate DAOs and beans from your data model.

Run the following command:

```bash
bin/console tdbm:generate
```

<div class="alert alert-danger">You must run this command after the installation of the package, and <strong>each time you run a migration</strong> (i.e. each time the database model changes).</div>

Accessing DAOs
--------------

The TDBM service provider will create one service per DAO.

The service name is the shot class name with the first character in lower case.

For instance:

- App\Daos\UserDao => userDao
- App\Daos\ProjectDao => projectDao


In Silex, you will typically access the DAOs fomr the `$app` variable passed in the closure:

```php
$app->get('test', function($app) {
    $user = $app['userDao']->getById($id);
    // do stuff
});
```

Next step
---------

Let's now learn how to [access the database](quickstart.md).
