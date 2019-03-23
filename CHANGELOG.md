5.1
===

New features:

- \#96: New @Bean annotation in table comments to alter the name of a bean
- \#95: Table and column annotations are now parsed using doctrine/annotations lexer/parser
- \#97: Generated DAOs and beans are now purged automatically. When a table is removed, the matching generated beans and daos will be removed too.
- \#116: New CodeGeneratorListernerInterface allows third party library to alter generated beans and DAOs on the fly (for the power users!)
- \#125: New @ProtectedGetter, @ProtectedSetter and @ProtectedOneToMany annotations (to be used in the DB column comments) enable generating beans with protected getters and setters

5.0
===

Breaking changes:

- TDBM is migrated to a new package name: thecodingmachine/tdbm
- TDBM is migrated to a new namespace: TheCodingMachine\TDBM
- Old package mouf/database.tdbm is still available for Mouf integration

Major changes:

- New website: https://thecodingmachine.github.io/tdbm/
- Adding support for MariaDB, PostgreSQL and Oracle
- Code coverage dramatically increased
- Code 100% type-hinted thanks to PHPStan and thecodingmachine/phpstan-strict-rules

Bugfixes:

- [a lot!](https://github.com/thecodingmachine/tdbm/milestone/1?closed=1)

4.3
===

Potentially breaking changes:

- TDBM now uses PHP 7.1 nullable return types and void types for base beans. **If your code overrides getter or setters** from base bean, and if you are not yet running PHP 7.2 (not released at the time of writing this), your extended method will have to strictly match the method of the base bean. You might have to add those nullable type hint and void type hint into your own getters and setters.

Major changes:

- TDBM now requires PHP 7.1+
- Bean properties are type-hinted using the new nullable types if columns are nullable
- Added support for a naming strategy (to change the name of generated beans and DAOs)
- Configuration has been moved to a new "Configuration" class
- Added support for generator listeners that are triggered when beans are created

Minor changes:

- Parameter "store in UTC" has been removed from TDBM user interface. The parameter was ignored since 4.0.

Bugfix:

- \#138: fixing `findOneByXXX` parameters in generated DAOs

4.2
===

Major changes:

- More powerful result sets
    - Result sets can now be sorted "a posteriori" (with the `withOrder` method)
    - Result sets can now be parametrized "a posteriori" (with the `withParameters` method)

4.1
===

Major changes:

- TDBM now requires PHP 7+
- Generated beans use the new scalar type-hinting PHP 7 feature for getters and setters (based on the type of the column in DB)

4.0
===

Major changes:

- Bean constructors no longer require a `TDBMService` to be created, they can be called with their own constructor
- Beans feature getters for tables pointing on the bean's table
- Beans now feature support for many-to-many relationship accessors!
- Generated beans now support the inheritance between tables (using primary keys as a foreign key technique)
- Results are no more simple arrays. They implement the [beberlei/porpaginas](https://github.com/beberlei/porpaginas) library: you can now get offset/limit and
  the total count from the result object.
- Beans do not have a `save` method any more. You need to use the DAO `save` method instead.
 
Minor changes:

- Improved support for singularizing names (countries => CountryBean instead of countries => CountrieBean, thanks to ICanBoogie/Inflector)
- Primary keys can now be updated
- In cursor more, queries can now be looped over several time (this will trigger the query several times in database)
- Base DAOs are now named `TableNameBaseDao` instead of `TableNameDaoBase` (more coherent with bean naming)
- Generated names for methods in beans has changed:
    - There is no longer getter and setters for "ID" columns that are foreign keys to other columns.
    - Foreign key getters and setters are now base on the column name rather than the table name.
      If you have a table "user" with a column "birth_country_id" pointing to a table "country", previously, TDBM was
      generating "getCountry/setCountry" methods along a "getCountryByBirthCountryId/setCountryByBirthCountryId" method.
      Now, it will only generate a "getBirthCountry/setBirthCountry" pair of methods.
- New `findObjectOrFail` method that performs a `findObject` and throws an exception if no result is found.