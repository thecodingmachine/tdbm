---
title: Configuring the naming of beans and DAOs
subTitle: 
currentMenu: configuring_naming
---

By default, if you have a `users` table, TDBM will generate those classes:

- `User` class for the main bean
- `AbstractUser` class for the "base" bean
- `UserDao` class for the main DAO
- `AbstractUserDao` class for the "base" DAO

Note: before TDBM 4.3, naming of beans was quite different:

- `UserBean` class for the main bean
- `UserBaseBean` class for the "base" bean
- `UserDao` class for the main DAO
- `UserBaseDao` class for the "base" DAO

These naming can be configured. To generate those names, TDBM relies on a **naming strategy**.
The default naming strategy is a class named `DefaultNamingStrategy` and implementing the `NamingStrategyInterface`.

When configuring the naming, you have 2 solutions:

- configure the default `DefaultNamingStrategy` instance
- provide your own `NamingStrategyInterface` implementation to TDBM

<div class="alert alert-info"><b>Note:</b> The naming strategy also covers:
<ul>
    <li>Name of getters</li>
    <li>Name of setters</li>
    <li>Name of find-by-index methods</li>
    <li>Name of JSON serialized properties</li>
</ul>
</div>

Configuring the default naming strategy
---------------------------------------

The default naming strategy assumes the name of the tables are in English and in plural form.
The default naming strategy will put the table name in CamelCase, in singular form and then add suffixes and prefixes.

Those suffixes and prefixes are configurable:

```php
// Let's create a naming strategy that maps behaviour of TDBM version <= 4.2
$strategy = new DefaultNamingStrategy();
$strategy->setBeanPrefix('');
$strategy->setBeanSuffix('Bean');
$strategy->setBaseBeanPrefix('');
$strategy->setBaseBeanSuffix('BaseBean');
$strategy->setDaoPrefix('');
$strategy->setDaoSuffix('Dao');
$strategy->setBaseDaoPrefix('');
$strategy->setBaseDaoSuffix('BaseDao');
```

Furthermore, you can configure a set of exceptions. This can be useful if your table names are not in English or not in plural form.
Let's assume you have a table named `chevaux` ('horses' in French). The singular form is 'cheval', so you would want a 'Cheval' bean and a 'ChevalDao'. That easy with the `setExceptions` method:

```php
$strategy->setExceptions([
    'chevaux' => 'Cheval'
]);
```

Using the @Bean annotation
--------------------------
<small>(Available in TDBM 5.1+)</small>

You can also use the @Bean annotation to directly alter the name of the beans/daos.

```sql
CREATE TABLE `members` (
  `id` varchar(36) NOT NULL,
  `login` varchar(50),
  PRIMARY KEY (`id`)
) COMMENT("@Bean(name=\"User\")");
```


Implementing your own naming strategy
-------------------------------------

If you need a more fine-grained control over the naming strategy, you can simply implement your own `NamingStrategyInterface` class. Or you can extend the `AbstractNamingStrategy` class that implements most of the boilerplate code you will need and still offers a large degree of freedom.

The naming strategy is passed as a parameter of the `Configuration` class used to configure the `TDBMService`.
