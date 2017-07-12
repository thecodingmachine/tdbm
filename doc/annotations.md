---
title: Annotations
subTitle: 
currentMenu: annotations
---

TDBM can read annotations to alter the generation of beans and DAOs.

If you have used annotations in the past, you are probably used to put annotations in your PHP documentation blocks.
But TDBM being a database-driven ORM, everything starts from the database. So TDBM will actually read annotations... from your database comments!

Right now, TDBM supports only one annotation.

The @UUID annotation
--------------------

By adding a `@UUID` annotation in your column comment, you inform TDBM that the column contains a generated random [UUID](https://en.wikipedia.org/wiki/Universally_unique_identifier#Version_1_.28date-time_and_MAC_address.29) value.

On object instantiation, TDBM will automatically fill the column with a random UUID. You would typically use this annotation in a primary key column.

```sql
CREATE TABLE `articles` (
  `id` varchar(36) NOT NULL COMMENT '@UUID',
  `content` varchar(255),
  PRIMARY KEY (`id`)
);
```

This will generate a bean with the following code:

```php
use Ramsey\Uuid\Uuid;

abstract class AbstractArticle extends AbstractTDBMObject implements \JsonSerializable
{
    public function __construct()
    {
        parent::__construct();
        $this->setId(Uuid::uuid1());
    }
    // ...
}
```

### Choosing the UUI version

By default, **UUID v1** is used. UUID v1 is timestamp-based. Therefore, your database rows will be sorted according to the creation order (just like with an autoincremented ID).

However, this also means that your ID contains the creation timestamp of the field. If this is a sensitive information that you want to hide, you can instead use UUID v4.

To do so, simply use the `@UUID v4` annotation like this:

```sql
CREATE TABLE `articles` (
  `id` varchar(36) NOT NULL COMMENT '@UUID v4',
  `content` varchar(255),
  PRIMARY KEY (`id`)
);
```
