---
title: Installing TDBM in Lumen
subTitle: 
currentMenu: install_lumen
---
<div class="text-center">
<svg xmlns="http://www.w3.org/2000/svg" class="iconic iconic-lightbulb" width="128" height="128" viewBox="0 0 128 128">
  <g class="iconic-metadata">
    <title>Lightbulb</title>
  </g>
  <defs>
    <clipPath id="iconic-size-lg-lightbulb-clip-0">
      <path d="M16.583 94h33.417v2.667l-39.083 6.375-.333-6.417z"></path>
    </clipPath>
    <clipPath id="iconic-size-lg-lightbulb-clip-1">
      <path d="M64 57l-14.5 8.25v16.75h-34.25l-1.5-16 50.25-23z"></path>
    </clipPath>
  </defs>
  <g data-width="64" data-height="128" class="iconic-lightbulb-lg iconic-container iconic-lg" display="inline" transform="translate(32)">
    <path stroke="#000" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" class="iconic-lightbulb-screw iconic-lightbulb-screw-4 iconic-property-accent iconic-property-stroke" d="M44 122l-23.834 4.001"></path>
    <path stroke="#000" stroke-width="4" stroke-linecap="round" class="iconic-lightbulb-screw iconic-lightbulb-screw-3 iconic-property-accent iconic-property-stroke" d="M48 112l-32 6" fill="none"></path>
    <path stroke="#000" stroke-width="4" stroke-linecap="round" class="iconic-lightbulb-screw iconic-lightbulb-screw-2 iconic-property-accent iconic-property-stroke" d="M48 102l-32 6" fill="none"></path>
    <path clip-path="url(#iconic-size-lg-lightbulb-clip-0)" stroke="#000" stroke-width="4" stroke-linecap="round" class="iconic-lightbulb-screw iconic-lightbulb-screw-1 iconic-property-accent iconic-property-stroke" d="M48 92.5l-32 6" fill="none"></path>
    <path clip-path="url(#iconic-size-lg-lightbulb-clip-1)" stroke="#000" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-6 iconic-property-stroke" d="M60 54l-20 9v18.75" fill="none"></path>
    <path clip-path="url(#iconic-size-lg-lightbulb-clip-1)" stroke="#000" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-5 iconic-property-stroke" d="M24 68v13.75" fill="none"></path>
    <path d="M47 94h-30c-1.657 0-3.221-1.325-3.493-2.959l-1.014-6.082c-.272-1.634.85-2.959 2.507-2.959h34c1.657 0 2.779 1.325 2.507 2.959l-1.014 6.082c-.272 1.634-1.836 2.959-3.493 2.959z" class="iconic-lightbulb-base iconic-property-fill"></path>
    <path stroke="#000" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-4 iconic-property-stroke" d="M60 38l-56 26" fill="none"></path>
    <path stroke="#000" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-3 iconic-property-stroke" d="M60 22l-56 26" fill="none"></path>
    <path stroke="#000" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-2 iconic-property-stroke" d="M60 6l-56 26" fill="none"></path>
    <path stroke="#000" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-1 iconic-property-stroke" d="M30 4.114l-26 11.886" fill="none"></path>
  </g>
  <g data-width="16" data-height="32" class="iconic-lightbulb-md iconic-container iconic-md" display="none" transform="scale(4) translate(8)">
    <path stroke="#000" stroke-linecap="round" class="iconic-lightbulb-screw iconic-lightbulb-screw-4 iconic-property-accent iconic-property-stroke" d="M5.5 31.5l5-1.429" fill="none"></path>
    <path stroke="#000" stroke-linecap="round" class="iconic-lightbulb-screw iconic-lightbulb-screw-3 iconic-property-accent iconic-property-stroke" d="M4.5 29.5l7-2" fill="none"></path>
    <path stroke="#000" stroke-linecap="round" class="iconic-lightbulb-screw iconic-lightbulb-screw-2 iconic-property-accent iconic-property-stroke" d="M4.5 27.5l7-2" fill="none"></path>
    <path stroke="#000" stroke-linecap="round" class="iconic-lightbulb-screw iconic-lightbulb-screw-1 iconic-property-accent iconic-property-stroke" d="M4.5 25.5l7-2" fill="none"></path>
    <path stroke="#000" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-5 iconic-property-stroke" d="M12.5 18.5l-2 1v3.5" fill="none"></path>
    <path stroke="#000" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-4 iconic-property-stroke" d="M5.5 23v-1.5" fill="none"></path>
    <path stroke="#000" stroke-width="3" stroke-linecap="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-3 iconic-property-stroke" d="M14.5 11.5l-13 6" fill="none"></path>
    <path stroke="#000" stroke-width="3" stroke-linecap="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-2 iconic-property-stroke" d="M14.5 5.5l-13 6" fill="none"></path>
    <path stroke="#000" stroke-width="3" stroke-linecap="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-1 iconic-property-stroke" d="M10.5 1.5l-9 4" fill="none"></path>
    <path d="M11 25h-6c-.552 0-1.142-.425-1.316-.949l-.367-1.103c-.175-.524.131-.949.684-.949h8c.552 0 .858.425.684.949l-.367 1.103c-.175.524-.764.949-1.316.949z" class="iconic-lightbulb-base iconic-property-fill"></path>
  </g>
  <g data-width="10" data-height="16" class="iconic-lightbulb-sm iconic-container iconic-sm" display="none" transform="scale(8) translate(3)">
    <path d="M7 14c0 1.105-.895 2-2 2s-2-.895-2-2" class="iconic-lightbulb-screw iconic-property-accent iconic-property-fill"></path>
    <path stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-4 iconic-property-stroke" d="M8 8l-2 1v2" fill="none"></path>
    <path stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-3 iconic-property-stroke" d="M4 10.5v.5" fill="none"></path>
    <path stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-2 iconic-property-stroke" d="M1 8l8-4" fill="none"></path>
    <path stroke="#000" stroke-width="2" stroke-linecap="round" class="iconic-lightbulb-coil iconic-lightbulb-coil-1 iconic-property-stroke" d="M7 1l-6 3" fill="none"></path>
    <path d="M1.776 12.553l-.553-1.106c-.123-.247 0-.447.276-.447h7c.276 0 .4.2.276.447l-.553 1.106c-.124.247-.448.447-.724.447h-5c-.276 0-.6-.2-.724-.447z" class="iconic-lightbulb-base iconic-property-fill"></path>
  </g>
</svg>

<p><strong>Lumen 5.x integration</strong></p>
</div>


## Installation

To install TDBM in Lumen 5.x:

```bash
composer require thecodingmachine/tdbm-laravel ^5.0
```

<div class="alert alert-info">There is no typo here. The <i>thecodingmachine/tdbm-laravel</i> can be used both for Laravel and Lumen integration.</div>


Then edit your `bootstrap/app.php` file and register these 2 service providers:

```php
$app->register(Nayjest\LaravelDoctrineDBAL\ServiceProvider::class);
$app->register(TheCodingMachine\TDBM\Laravel\Providers\TdbmServiceProvider::class);
```

<div class="alert alert-info">The <code>Nayjest\LaravelDoctrineDBAL\ServiceProvider</code> provides a "Doctrine DBAL connection" needed by TDBM. This connection reuses the default database connection used by Lumen.</div>

## Generating beans and DAOs

When installation is done, you need to generate DAOs and beans from your data model.

Run the following command:

```bash
php artisan tdbm:generate
```

<div class="alert alert-danger">You must run this command after the installation of the package, and <strong>each time you run a migration</strong> (i.e. each time the database model changes).</div>

## Advanced configuration

By default, TDBM will write DAOs in the `App\Daos` namespace and beans in the `App\Beans` namespace.
If you want to customize this, you can edit the `bootstrap/app.php` file:

```php
config([
    'database.tdbm.daoNamespace' => 'App\\Daos',
    'database.tdbm.beanNamespace' => 'App\\Beans'    
]);
```

Accessing DAOs through a route closure
--------------------------------------

In Lumen, you would typically inject the DAOs in your route closure.

**bootstrap/app.php**
```php
use App\Daos\UserDao;

// ...

$app->get('test', function(UserDao $userDao) {
    $user = $this->userDao->getById($id);
    // do stuff
});
```


Accessing DAOs through a Controller
-----------------------------------

Alternatively, if you use controllers, you can also inject the DAOs in your controllers constructor (or in any class resolved by the Lumen container).

Typically:

**bootstrap/app.php**
```php
$app->get('test', [
    'uses' => 'TestController@index'
]);
```

**app/Http/Controllers/TestController.php**
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
