Migrating to TDBM 4
===================

TDBM 4 is almost a complete rewrite compared to TDBM 3.x. More than 90% of the code has been rewritten.

The philosophy of TDBM stays the same (write table, generate DAOs and Beans, auto-discover links). However, the
details are different in many ways, so if you plan to migrate from TDBM 3, you **will have to rewrite** a big part of 
your database access code.

Below is a short list of the biggest changes:
 
- New beans are instantiated directly.
- `DaoInterface` has been deprecated and removed. There is no more `create` method in the DAOs.
- There are no more getters and setters for foreign key ids. Instead, you can use the getters and setters of beans
  directly.
- All TDBM xxxFilters have been removed. They are replaced by pure SQL and the "magic parameters" mechanism.
- Many to many relationships are automatically detected. Pivot tables no more generate beans and DAOs.
- Returned results are now objects that can be iterated. They implement the `porpaginas` interface: pagination is 
  done on the result set rather than when calling the `getObjects/findObjects` method. As a result, the real query
  is performed when the result is first iterated and not when the `getObjects/findObjects` method is called.
- Many methods have been renamed:
    - `getObjects` => `find`
    - `getObject` => `findOne`
    - `getList` => `findAll`
- Inheritance is automatically detected in tables and generates beans with inheritance
