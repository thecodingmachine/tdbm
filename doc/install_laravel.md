---
title: Installing TDBM in Laravel
subTitle: 
currentMenu: install_laravel
---

TDBM comes with a dedicated package for Laravel 5.x integration.

## Installation

To install TDBM in Laravel 5.x:

```bash
composer require thecodingmachine/tdbm-laravel ^5.0
```

Then:

* Register `Nayjest\LaravelDoctrineDBAL\ServiceProvider` in your application configuration file (`config/app.php`)
* Register `TheCodingMachine\TDBM\Laravel\TDBMServiceProvider` in your application configuration file (`config/app.php`)

<div class="alert alert-info">The <code>Nayjest\LaravelDoctrineDBAL\ServiceProvider</code> provides a "Doctrine DBAL connection" needed by TDBM. This connection reuses the default database connection used by Laravel.</div>

## Generating beans and DAOs

When installation is done, you need to generate DAOs and beans from your data model.

Run the following command:

```bash
php artisan tdbm:generate
```

<div class="alert alert-danger">You must run this command after the installation of the package, and **each time you run a migration** (i.e. each time the database model changes).</div>

## Advanced configuration

By default, TDBM will write DAOs in the `App\Daos` namespace and beans in the `App\Beans` namespace.
If you want to customize this, you can edit the `config/database.php` file:

```php
<?php

return [

    // ...

    /*
    |--------------------------------------------------------------------------
    | TDBM Configuration
    |--------------------------------------------------------------------------
    |
    | Use this configuration to customize the namespace of DAOs and beans.
    | These namespaces must be autoloadable from Composer.
    | TDBM will find the path of the files based on Composer.
    |
    */


    'tdbm' => [
        'daoNamespace' => 'App\\Daos',
        'beanNamespace' => 'App\\Beans',
    ]
];
```

Usage
-----

In Laravel, you would typically inject the DAOs in your services/controllers constructor.

Typically:

```php
<?php
namespace App\Http\Controllers;

use App\Daos\MigrationDao;
use Illuminate\Http\Request;

class TestController extends Controller
{
    /**
     * @var UserDao
     */
    private $userDao;

    /**
     * The DAO we need is injected in the constructor
     */
    public function __construct(UserDao $userDao)
    {
        $this->userDao = $userDao;
    }

    public function index($id)
    {
        $user = $this->userDao->getById($id);
        // do stuff
    }
}
```


Next step
---------

Let's now learn how to [access the database](quickstart.md).
