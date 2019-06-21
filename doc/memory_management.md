---
title: Memory management and batches processing
subTitle: 
currentMenu: memory_management
---

Starting with TDBM 3.3+, you can use advanced methods for a more efficient memory consumption.

This is typically useful when you want to fetch large datasets from the database.

Let's assume you have a big "users" table with 100.000 records and you want to perform some processing on each row of the dataset.
You will typically write:

```php
$users = $userDao->findAll();

foreach ($users as $user) {
	// Do stuff
}
```

If your dataset is big enough, you are almost sure to get an *out of memory* error.

Why? The `$users` array keeps a reference of all `User` objects
that have been fetched from the database. Therefore, as the array fills, the memory gets low.

What you want to do is to get a **cursor** instead of an array of results. As you iterate the
cursor, it will not keep a reference of records that have already been processed in the `foreach` loop.

To do this, you simply need to use the **cursor** mode:

```php
$tdbmService->setFetchMode(TDBMService::MODE_CURSOR);

$users = $userDao->findAll();

foreach ($users as $user) {
	// Do stuff
}
```

In **cursor** mode, the `$users` variable becomes a "cursor".

<div class="alert alert-danger">Using the cursor mode has a number of consequences. Since the results
are not stored in memory, if you perform 2 successive <code>foreach</code> calls on the result set,
the SQL query will be executed twice by the database.<br/>
Furthermore, you cannot access the data set via indexes (like you would in a 
typical array).</div>

If you retry the sample code using **cursor** mode, you will see that your processing will begin,
but you will still end up with an *out of memory* error.

Why? Because TDBM keeps a second reference to all beans in the TDBMService. You do not have direct
access to this reference, but hopefully, you don't have to bother about it. All you need to do
is install the [**weakref**](http://php.net/manual/fr/class.weakref.php) extension. With this extension
enabled, and cursor mode enabled, TDBM will be able to manage memory much more efficiently,
and only keep track of beans you are actively using.

<div class="alert alert-info">To sum up, if you have very large datasets to fetch, you should
use the <strong>cursor</strong> mode on your data sets and <strong>install the weakref PHP
extension</strong>.</div>

<div class="alert alert-warning">The weakref extension is not available in PHP 7.3+ because of internal changes
in the PHP engine. PHP 7.4 will <a href="https://wiki.php.net/rfc/weakrefs">feature a native Weakref implementation when it is released</a>.
If you need weakref, stick with PHP 7.2 and jump to PHP 7.4 when it is available!</div>
