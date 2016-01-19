Miscellaneous features
======================

Discarding changes on a bean
----------------------------

If your bean is coming from database, you can at any time cancel any changes you performed using the `discardChanges` 
method.

```php
$user = $userDao->getById(1);

echo $user->getName();
// outputs 'Foo'

$user->setName('Bar');

// Cancels any changes
$user->discardChanges();

echo $user->getName();
// outputs 'Foo' again, not 'Bar'
```


Cloning beans
-------------

You can clone beans using the `clone` keyword.

```php
$user = $userDao->getById(1);

$userCopy = clone $user;
```

Cloned beans have the following properties:

- The primary key of the cloned bean is set to `null`. This enables you to `save` the cloned bean easily (if you use auto-incremented primary keys).
- The cloned bean is "detached" from TDBM. You will have to call the `save` method of the DAO to save the bean in database.
- Many to many relationships are also passed to the cloned bean.

