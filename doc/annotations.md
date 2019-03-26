---
title: Annotations
subTitle: 
currentMenu: annotations
---

TDBM can read annotations to alter the generation of beans and DAOs.

If you have used annotations in the past, you are probably used to put annotations in your PHP documentation blocks.
But TDBM being a database-driven ORM, everything starts from the database. So TDBM will actually read annotations... from your database comments!

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

### Choosing the UUID version

By default, **UUID v1** is used. UUID v1 is timestamp-based. Therefore, your database rows will be sorted according to the creation order (just like with an autoincremented ID).

However, this also means that your ID contains the creation timestamp of the field. If this is a sensitive information that you want to hide, you can instead use UUID v4.

To do so, simply use the `@UUID("v4")` annotation like this:

```sql
CREATE TABLE `articles` (
  `id` varchar(36) NOT NULL COMMENT '@UUID("v4")',
  `content` varchar(255),
  PRIMARY KEY (`id`)
);
```

The @Autoincrement annotation
-----------------------------

<div class="alert alert-danger">The @Autoincrement annotation is mostly useful with Oracle databases</div>

**You won't need this annotation if you are using MySQL or PostgreSQL.**

On some database platforms (namely *Oracle*), there is no native support for auto-incremented IDs. However, these can be "emulated" using a database trigger and a sequence.

However, when TDBM will read the model, it will not be able to understand that your column is auto-incremented via a trigger. So you have to tell TDBM that your column is auto-incremented manually. You do this by adding the `@Autoincrement` comment in the column description.

```sql
CREATE TABLE departments (
  ID           NUMBER(10)    NOT NULL COMMENT '@Autoincrement',
  DESCRIPTION  VARCHAR2(50)  NOT NULL);

ALTER TABLE departments ADD (
  CONSTRAINT dept_pk PRIMARY KEY (ID));

CREATE SEQUENCE dept_seq START WITH 1;

CREATE OR REPLACE TRIGGER dept_bir 
BEFORE INSERT ON departments 
FOR EACH ROW

BEGIN
  SELECT dept_seq.NEXTVAL
  INTO   :new.id
  FROM   dual;
END;
/
```

The @Bean annotation
--------------------
<small>(Available in TDBM 5.1+)</small>

This annotation can be put on a table comment to alter the name of the generated bean.

```sql
CREATE TABLE `members` (
  `id` varchar(36) NOT NULL,
  `login` varchar(50),
  PRIMARY KEY (`id`)
) COMMENT("@Bean(name=\"User\")");
```

In the example above, the bean class name will not be `Member` but `User`.
The name of the DAO will also be changed from `MemberDao` to `UserDao`.

<div class="alert alert-info">Note: the @Bean annotation is read by the <a href="configuring_naming.md">default naming strategy</a> provided by TDBM.
If you use your own naming strategy, the @Bean annotation will be ignored unless you explicitly code it back in your
naming strategy.</div>


The @ProtectedGetter and @ProtectedSetter annotations
-----------------------------------------------------
<small>(Available in TDBM 5.1+)</small>

These annotations can be put on a column comment to alter the visibility of the generated getter.

```sql
CREATE TABLE `users` (
  `id` INTEGER NOT NULL,
  `login` varchar(255),
  `password` varchar(255) COMMENT '@ProtectedGetter',
  `status` INTEGER COMMENT '@ProtectedSetter',
  PRIMARY KEY (`id`)
);
```

In the example above, the `getPassword` method and the `setStatus` method will be protected.

Use the `@ProtectedGetter` and `@ProtectedSetter` if you want to avoid an "anemic" data model.

The getters and the setters are only available from the class itself and you can instead add methods more
"domain-oriented".

For instance:

```php
class User extends AbstractUser
{
    public function enableUser(): void {
        $this->setStatus(1);
    }
    
    public function disableUser(): void {
        $this->setStatus(0);
    }
    
    public function setPassword(string $password): void {
        parent::setPassword(password_hash($password, PASSWORD_DEFAULT));
    }
    
    public function verifyPassword(string $password): bool {
        return password_verify($password, $this->getPassword());
    }
    
}
```

Hiding the getter and setter makes your bean more reliable and easier to use.

By hiding `setStatus` and replacing it with `enableUser/disableUser`, you make sue
that the developer using the User bean cannot set an invalid status.

Making the `getPassword` method protected, you cannot even get the hashed password from the bean, only verify it.

Notice: a complete implementation should also check if a password needs rehashing but this is going beyond the scope of this simple example.

<div class="alert alert-info">When you use the `@ProtectedGetter` annotation, TDBM will assume the column
access is sensitive and will therefore prevent the column from being JSON serialized.</div>

The @ProtectedOneToMany annotation
----------------------------------
<small>(Available in TDBM 5.1+)</small>

This annotation can be put on a column comment to alter the visibility of the "inverse" relationship.

For instance, if you put the `@ProtectedOneToMany` on the "country_id" column of a "users" table,
then in the `Country` bean, the `getUsers()` method will be protected.


The @AddInterface annotation
----------------------------
<small>(Available in TDBM 5.1+)</small>

Use this annotation in a table comment to force a bean to implement a given PHP interface.

```sql
CREATE TABLE `members` (
  `id` varchar(36) NOT NULL,
  `login` varchar(50),
  PRIMARY KEY (`id`)
) COMMENT("@AddInterface(\"App\\MemberInterface\")");
```

Why this annotation?

If you are developing an application, it is likely you will not need this annotation. You can simply edit your bean
and add an `implements` clause in the class declaration.

However, if you are developing a library meant to be used by other developers, you can provide a SQL patch that will
alter the comments of the table. The `implements` clause will be added by TDBM on the base bean class automatically.
Therefore, this annotation allows third party-libraries to add interfaces to your beans.

This annotation is particularly powerful when used in conjunction with the `@AddTrait` annotation.

The @AddInterfaceOnDao annotation
---------------------------------
<small>(Available in TDBM 5.1+)</small>

This annotation is similar to the `@AddInterface` annotation but it adds the interface on the DAO instead of the Bean.
Use this annotation in a table comment to force a DAO to implement a given PHP interface.

```sql
CREATE TABLE `members` (
  `id` varchar(36) NOT NULL,
  `login` varchar(50),
  PRIMARY KEY (`id`)
) COMMENT("@AddInterfaceDao(\"App\\MemberDaoInterface\")");
```

The @AddTrait annotation
------------------------
<small>(Available in TDBM 5.1+)</small>

Use this annotation in a table comment to force a bean to use a given PHP trait.

```sql
CREATE TABLE `members` (
  `id` varchar(36) NOT NULL,
  `login` varchar(50),
  PRIMARY KEY (`id`)
) COMMENT("@AddTrait(\"App\\MemberTrait\")");
```

This annotation is very useful to third party libraries that provide a table and want to ship a default behaviour for the beans
associated with the table.

You can also use the "modifiers" attribute of the annotation to override or alias some methods of the traits:

```sql
CREATE TABLE `members` (
  `id` varchar(36) NOT NULL,
  `login` varchar(50),
  PRIMARY KEY (`id`)
) COMMENT("@AddTrait(name=\"App\\MemberTrait\"
           modifiers={\"\\App\\MemberTrait::myMethod insteadof OtherTrait\",
                      \"\\App\\OtherTrait::myMethod as myRenamedMethod\"}
           )");
```

The @AddTraitOnDao annotation
-----------------------------
<small>(Available in TDBM 5.1+)</small>

This annotation is similar to the `@AddTrait` annotation but it adds the trait on the DAO instead of the Bean.

```sql
CREATE TABLE `members` (
  `id` varchar(36) NOT NULL,
  `login` varchar(50),
  PRIMARY KEY (`id`)
) COMMENT("@AddTraitOnDao(\"App\\MemberDaoTrait\")");
```

