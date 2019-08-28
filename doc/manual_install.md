---
title: Manual installation
subTitle: 
currentMenu: manual_install
---

At the core of TDBM, there is the `TDBMService` class. This service is used to generate the PHP code, and also by all DAOs to retrieve beans from the database.

The `TDBMService` constructor takes a `Configuration` object that contains all the configuration needed by TDBM.

Depending [on the framework you are using](install.md) (and the integration package you chose), the way the `Configuration` object is set-up will vary.

Hopefully, if your framework is not supported yet (or if you use no framework), setting up TDBM yourself is quite easy to do.

## Installing TDBM

Simply run:

```php
$ composer require thecodingmachine/tdbm ^5.1
```

## Instantiating the TDBMService

At minimum, you need a [Doctrine database `Connection`](http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html) and a [Doctrine cache object](http://doctrine-orm.readthedocs.io/projects/doctrine-orm/en/latest/reference/caching.html).

We strongly advise to use the `APCuCache` from Doctrine that will yield the best performances.

Without using any framework, a working TDBM setup could look like this:

```php
$config = new \Doctrine\DBAL\Configuration();

$connectionParams = array(
    'user' => 'mysql_user',
    'password' => 'mysql_password',
    'host' => 'localhost',
    'driver' => 'pdo_mysql',
    'dbname' => 'my_db',
);

$dbConnection = Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);

// The bean and DAO namespace that will be used to generate the beans and DAOs. These namespaces must be autoloadable from Composer.
$beanNamespace = 'MyApp\\Beans';
$daoNamespace = 'MyApp\\Daos';

$cache = new Doctrine\Common\Cache\ApcuCache();

$logger = new Monolog\Logger(); // $logger must be a PSR-3 compliant logger (optional).

// Let's build the configuration object
$configuration = new TheCodingMachine\TDBM\Configuration(
    $beanNamespace,
    $daoNamespace,
    $dbConnection,
    null,    // An optional "naming strategy" if you want to change the way beans/DAOs are named
    $cache,
    null,    // An optional SchemaAnalyzer instance
    $logger, // An optional logger
    []       // A list of generator listeners to hook into code generation
);

// The TDBMService is created using the configuration object.
$tdbmService = new TDBMService($configuration);
```

Ok, we have our `$tdbmService`. What's next?

## Generating DAOs and beans

<div class="alert alert-danger">You must regenerate DAOs and beans <strong>each time your database model changes</strong>.</div>

### Using PHP code

In order to generate the DAOs and beans, you simply need to call the `generateAllDaosAndBeans` method:

```php
$tdbmService->generateAllDaosAndBeans();
// Shazam! All PHP files have been written!
```

### Using Symfony console

If your application supports the [Symfony console](http://symfony.com/doc/current/components/console.html), Mouf comes with a "tdbm:generate" command that will generate those DAOs and beans:

```php
// The command takes in parameter the same Configuration object used by the TDBMService.
$command = new TheCodingMachine\TDBM\Commands\GenerateCommand($configuration);

// $application is your Symfony console object
$application->add($command);
```

## Instantiating DAOs

You now have one DAO per database table. In order to create a DAO, you simply need pass it the `$tdbmService`.

```php
$userDao = new UserDao($tdbmService);

$user = $userDao->getById(42);
echo $user->getLogin();
```

## Integrating TDBM in your own framework

The code samples above are here to help you understand the way `TDBMService` is instantiated.

Of course, in a real application, you will want to put the `TDBMService` and every generated DAOs in a container.
You will typically inject the DAOs in your code and will seldom use the `TDBMService` directly.

## Next step

Let's now learn how to [access the database](quickstart.md).
