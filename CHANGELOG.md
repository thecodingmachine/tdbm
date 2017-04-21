4.3
===

Major changes:

- TDBM now requires PHP 7.1+
- Bean properties are type-hinted using the new nullable types if columns are nullable
- Added support for a naming strategy (to change the name of generated beans and DAOs)
- Configuration has been moved to a new "Configuration" class
- Added support for generator listeners that are triggered when beans are created

Minor changes:

- Parameter "store in UTC" has been removed from TDBM user interface. The parameter was ignored since 4.0.


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