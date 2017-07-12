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

By adding a `@UUID` annotation in your column comment, you inform TDBM that the column contains a generated random [UUID v1](https://en.wikipedia.org/wiki/Universally_unique_identifier#Version_1_.28date-time_and_MAC_address.29) value.

On object instantiation, TDBM will automatically fill the column with a random UUID. You would typically use this annotation in a primary key column.

```sql
CREATE TABLE `articles` (
  `id` varchar(36) NOT NULL COMMENT '@UUID',
  `content` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
);
```
