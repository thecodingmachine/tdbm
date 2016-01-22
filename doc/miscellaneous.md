Miscellaneous features
======================

Deleting beans
--------------

Each DAO comes with a `delete` method to delete beans from the database.

```php
$userDao->delete($user);
```

When `delete` is called on a bean, the many-to-many relationships are automatically deleted as well.

###Cascading deletes

The `delete` method accepts a second `$cascade` parameter, that can be set to true is you want to perform cascade delete operations.

```php
// Delete $user and any bean that is pointing to the user.
$userDao->delete($user, true);
```

When using "cascading delete", any bean pointing to the deleted bean will be deleted as well.

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

Mapping result sets
-------------------

Please notice that result sets have a very useful `map` method.

Let's say you want to build an array of all your users first name:

```php
$users = $userDao->findAll();

// The callback passed to the map function will be called once for each record in the recordset.
// You will get in $firstNames an array containing the list of callback results.
$firstNames = $users->map(function(UserBean $user) {
    return $user->getFirstName();
});
```

