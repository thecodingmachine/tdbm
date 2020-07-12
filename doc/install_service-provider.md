---
title: Installing TDBM in container-interop/service-provider compatible container
subTitle: 
currentMenu: install_service-provider
---

## Installation

You can install TDBM 5 in any framework that can load service-providers compatible with [the container-interop/service-provider interface](https://github.com/container-interop/service-provider/).

In this document, we will describe integration of TDBM 5 with [Simplex](https://github.com/mnapoli/simplex). Your mileage may vary based on the container you are using.

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  },
  "require": {
    "php": ">=7.2",
    "mnapoli/simplex": "^0.4.1",
    "thecodingmachine/tdbm-universal-provider": "^5",
    "thecodingmachine/discovery": "^1.2"
  },
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

<div class="alert alert-info">
We are also importing <a href="https://thecodingmachine.github.io/discovery/">thecodingmachine/discovery</a> that let's us import all container-interop/service-providers automatically.
</div>

The *thecodingmachine/tdbm-universal-provider* package has dependencies on a number of other service providers that will be loaded automatically:

- "thecodingmachine/dbal-universal-module": provides a DBAL connection to the database
- "thecodingmachine/stash-universal-module": provides a Stash cache
- "thecodingmachine/psr-6-doctrine-bridge-universal-module": provides a bridge between Doctrine cache and Stash
- "thecodingmachine/symfony-console-universal-module": provides a Symfony console for your application (at `vendor/bin/app_console`)

### Creating a container with everything we need



Then let's create a `container.php` file at the root of your project and let's fill it with all service-providers:

**container.php**
```php
<?php
use Simplex\Container;
use TheCodingMachine\Discovery\Discovery;
use Interop\Container\ServiceProviderInterface;

$serviceProviders = [];

foreach (Discovery::getInstance()->get(ServiceProviderInterface::class) as $serviceProviderName)
{
    $serviceProviders[] = new $serviceProviderName();
}

$container = new Container($serviceProviders);

// The settings for the database must be stored in the container.
// See https://github.com/thecodingmachine/dbal-universal-module for more information
$container->set('dbal.dbname', 'my_database');
$container->set('dbal.host', 'localhost');
$container->set('dbal.user', 'root');
$container->set('dbal.password', '');
$container->set('dbal.port', 3306);

return $container;
```

<div class="alert alert-info">
Important: the file name MUST be `container.php`, it MUST be at the root of your project or in the "config" directory.
It MUST return the container.
Otherwise, the Symfony console service provider will not find it.
</div>


## Generating beans and DAOs

When installation is done, you need to generate DAOs and beans from your data model.

Run the following command:

```bash
vendor/bin/app_console tdbm:generate
```

<div class="alert alert-danger">You must run this command after the installation of the package, and <strong>each time you run a migration</strong> (i.e. each time the database model changes).</div>

Accessing DAOs
--------------

The TDBM service provider will create one service per DAO.

The service name is the fully qualified class name of the DAO.

For instance:

- `App\Daos\UserDao` => `$container->get('App\\Daos\\UserDao')`

Advice
------

We strongly recommend using TDBM along a migration tool. For instance, Doctrine migrations.
Since you use container-interop/service-providers, you can benefit from the [Doctrine Migrations universal module](https://github.com/thecodingmachine/doctrine-migrations-universal-module).

Next step
---------

Let's now learn how to [access the database](quickstart.md).
