---
title: Installing TDBM in Symfony
subTitle: 
currentMenu: install_symfony
---

## Installation

To install TDBM in Symfony 4.x:

```bash
composer require thecodingmachine/tdbm-bundle ^5.0
```

<div class="alert alert-info">
TDBM requires Doctrine DBAL to be available. In Symfony 4+, DBAL is available as a separated bundle.
Therefore, installing the TDBM bundle will install Doctrine ORM bundle a dependency. Nothing to worry 
about, this is unfortunately an expected behaviour ([more details](https://github.com/symfony/recipes/issues/218))
</div>

### Configuration

The database connection is configured like any other Symfony application, using the `DATABASE_URL` environment variable.

The rest of the parameters are stored in the `config/packages/tdbm.yaml` file.

By default, beans will go into `App\Beans` and DAOs will go into `App\Daos`.

<div class="alert alert-warning">
If your default namespace for your application is not <code>App</code>, you should open this <code>tdbm.yaml</code> file and customize the 
<code>tdbm.bean_namespace</code> and <code>tdbm.dao_namespace</code> to match your application namespace.
</div>

You can also use the `config/packages/tdbm.yaml` file to customize the naming of beans and DAOs.

Here is a configuration file with all available options:

**config/packages/tdbm.yaml**
```yaml
tdbm:
    bean_namespace: App\Beans
    dao_namespace: App\Daos
    # The naming section is completely optional
    naming:
        bean_prefix: ""
        bean_suffix: ""
        base_bean_prefix: Abstract
        base_bean_suffix: ""
        dao_prefix: ""
        dao_suffix: Dao
        base_dao_prefix: Abstract
        base_dao_suffix: Dao
        # Exceptions are used to transform a table name (the key) into a bean name (the value)
        # Very useful if your database table names are not in english 
        # (as TDBM will try to turn plural table names into singular bean names)
        exceptions:
            chevaux: Cheval
```

## Generating beans and DAOs

When installation is done, you need to generate DAOs and beans from your data model.

Run the following command:

```bash
bin/console tdbm:generate
```

<div class="alert alert-danger">You must run this command after the installation of the package, and <strong>each time you run a migration</strong> (i.e. each time the database model changes).</div>

Accessing DAOs
--------------

Because Symfony 4 comes by default with auto-wiring of services, you can simply inject the generated DAOs in your
controllers or services to use them.

```php
namespace App\Controller;

use App\Daos\UserDao;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class TestController extends Controller
{

    /**
     * @var UserDao
     */
    private $userDao;

    public function __construct(UserDao $userDao)
    {

        $this->userDao = $userDao;
    }

    /**
     * @Route("/test", name="test")
     */
    public function index()
    {
        return $this->json($this->userDao->findAll());
   }
}```

Next step
---------

Let's now learn how to [access the database](quickstart.md).
