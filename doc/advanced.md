---
title: Advanced tutorial
subTitle: 
currentMenu: advanced
---

In this advanced tutorial, we will learn the quirks of the `find` method in details, and see the behaviour of TDBM with more complex data model.
If you are new to TDBM, you should start with the [quickstart guide](quickstart.md).
	
About ambiguity
---------------

In the samples of the quick start guide, everything happens to be fine because the object model is quite simple. In 80% of the cases you will encounter, you will be able to stick to the access model presented in the quick start guide. There are, however, cases where there could be several ways to join two tables, as shown below.

![database schema](images/schema2.png)

In the example above, we added a company table. A company is attached to a country. A user is attached to a company, and still, a user is attached to a country (for instance, its birth place). Therefore, a user can be attached to a country that is different from the country of its company.

Now, what if we write this piece of code:

```php
class UserDao implements AbstractUserDao
{
    /**
     * Returns users whose country is $country
     */
    public findByCountry(Country $country) : iterable
    {
        return $this->find($country);
    }
}
```

Will we get the users who are born in `$country` or will we get the users whose company's country is `$country`? The code above is to some extent **ambiguous**. How will TDBM decide?

The answer lies in the first design choice of TDBM: _Simplicity_. TDBM will choose the simplest way to go because it is likely that this is what the developer meant. Here, obviously, the developer meant that he wanted the country of the user, so this is what he will get.
For TDBM, the simplest way is the way **that requires the less joins between tables** to get to the result.

This rule works for most of the problems you will encounter. However, there are still cases where ambiguity can happen, and cannot be resolved by TDBM. Have a look at the schema below:

![database schema](images/schema3.png)

In this schema, a user is linked to 2 countries. One is its birth country and one is the country he works in. Here, TDBM cannot make any decision... If the user writes

```php
class UserDao implements AbstractUserDao
{
    /**
     * Returns users whose country is $country
     */
    public findByCountry(Country $country) : iterable
    {
        return $this->find($country);
    }
}
```

there is no way to know if it more likely that he wanted users from the birth country or from the work country. So TDBM will throw an **ambiguity exception**. This exception will inform the user that its request is ambiguous and that he should solve it.

This exception message is quite clear on the ambiguity.

Solving ambiguities is always possible by [manually specifying joins](quickstart.md#joins-ans-filters) in your queries.

<div class="alert alert-warning">Note: If your data model has a lot of loops, you will likely face a lot of ambiguities. TDBM is very well suited for simple models. If your database model is complex enough to feature several of these loops, maybe TDBM is not the best tool for you. There are other great tools more suited for those use cases like <a href="http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/">Doctrine ORM</a> for instance.</div>
