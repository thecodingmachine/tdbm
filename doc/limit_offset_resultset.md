---
title: Playing with result sets
subTitle: 
currentMenu: limit_offset_resultset
---

Paginating data with limits and offsets
---------------------------------------

When you perform a query with TDBM, you never pass the limit or offset to the DAO method you are querying.

```php
$users = $userDao->findAll();

// Iterate all users
foreach ($users as $user) {
    // Do stuff...
}
```

If you want to limit the number of records returned, or start at a given offset, you can use the `take` method.

```php
$users = $userDao->findAll();

$page = $users->take(0, 10);

// Iterate only the first 10 records
foreach ($page as $user) {
    // Do stuff...
}
```

What is going on behind the scene? When you query a TDBM DAO for a list, instead of returning an array containing the
results, TDBM will return a `ResultIterator` object. This object behaves like an array, so you can call `foreach` on it.
It is important to understand that the SQL query is not actually performed until `foreach` is called! This is
actually a nice thing, since we can call additional methods afterwards to modify the query. Like the `take` method.

Sorting
-------

We can also modify the sorting of your result set using the `withOrder` method:

```php
$users = $userDao->findAll();

// Changes the order of the query.
// Warning, this returns a new ResultIterator object!
$users = $users->withOrder('name asc, firstname asc');

foreach ($users as $user) {
    // Do stuff...
}
```

Parameters
----------

We can modify the named parameters of a query with the `withParameters` method:

```php
$users = $this->find("login LIKE :login");

// Changes the parameters of the query.
// Warning, this returns a new ResultIterator object!
$users = $users->withParameters([ 'login' => 'david%' ]);

foreach ($users as $user) {
    // Do stuff...
}
```

Mapping result sets
-------------------

Please notice that result sets have a very useful `map` method.

Let's say you want to build an array of all your users first name:

```php
$users = $userDao->findAll();

// The callback passed to the map function will be called once for each record in the recordset.
// You will get in $firstNames an array containing the list of callback results.
$firstNames = $users->map(function(User $user) {
    return $user->getFirstName();
});
```

ResultIterator utility methods
------------------------------

Here is a list of the methods you can call on a `ResultIterator`:

- `take($offset, $limit)`: this will add an OFFSET and a LIMIT to the query performed. The `take` method returns a 
  `PageIterator` instance that represents the "limited" results.
- `count()`: will return the total count of records.
- `first()`: will return the first element of the result set.
- `toArray()`: will cast the `ResultIterator` into a plain old PHP array.
- `map(callable $callback)`: will call the `$callback` on every bean of the recordset and return the matching array.
- `withOrder($orderBy) : ResultIterator`: changes the ORDER BY clause of your query (returns a **new** ResultIterator)
- `withParameters($parameters) : ResultIterator`: changes the named parameters of your query (returns a **new** ResultIterator)

Pages (retrieved with the `take` method) also have additional methods you can call:

- `count()`: will return the count of records in the page
- `totalCount()`: will return the total count of records (bypassing the limit and offset)
- `getCurrentOffset()`: returns the offset
- `getCurrentLimit()`: returns the limit
- `getCurrentPage()`: returns the page number (starting at one and based on the limit and offset given)
- `toArray()`: will cast the `PageIterator` into a plain old PHP array.
- `map(callable $callback)`: will call the `$callback` on every bean of the page and return the matching array.


Next step
---------

Let's now learn how to [regenerate DAOs](generating_daos.md) when your data model changes.
Or if you want to have a look at advanced stuff, learn how to [model inheritance in your database](modeling_inheritance.md).
