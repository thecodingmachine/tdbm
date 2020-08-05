---
title: Compatibility
subTitle: Compatibility with databases
currentMenu: compatibility
---

TDBM uses *Doctrine DBAL* under the hood to access your database.
*Doctrine DBAL* provides a solid abstraction layer allowing TDBM to be compatible with many databases out of the box.

TDBM is tested with these databases:

- MySQL (5.7 and 8.0)
- MariaDB (10.x)
- PostgreSQL (tested on v12, but v9, v10 and v11 should be compatible)
- Oracle (11g)

TDBM is **not** compatible with Sqlite (because the database does not support foreign keys properly)


# Known vendor issues

## Oracle 11g

### JSON columns are not supported

Oracle 11g does not have a notion of JSON column and DBAL casts those columns in CLOB.
But CLOB columns have an issue (they cannot be part of a DISTINCT query) that is used by TDBM.
As a result, any request on a table containing a JSON column will fail.

### BLOB columns are not supported

Due to a limitation in the way OCI8 driver works, the Oracle database cannot accept *streams*
in BLOB columns (it only accepts strings). But TDBM is exclusively using *streams* for BLOB
columns. As a result, TDBM does not support any kind of BLOB column in Oracle. 
