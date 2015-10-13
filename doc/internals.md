TDBM internals
==============

AbstractTDBMObject
------------------

All beans extend the `AbstractTDBMObject` class.
This class contains the following properties:

- `$dbRow`: the data contained by the object, as an array of column => value
- `$primaryKeys`: the list of primary keys to access this object, as an array of column => value
- `$status`: the status of the current object

Statuses
--------

Beans can have the following statuses:

- `TDBMObjectStateEnum::STATE_DETACHED`: when an object has been created with the `new` keyword and is not yet
  aware of the `TDBMService` instance.
    - `AbstractTDBMObject::$primaryKeys`: *empty*
    - `TDBMService::$toSaveObjects`: *no*
    - `TDBMService::$objectStorage`: *no*
- `TDBMObjectStateEnum::STATE_NEW`: when an object is known from `TDBMService` but not yet stored in database.
  This happens after calling the `TDBMService->attach` method on a detached object.
    - `AbstractTDBMObject::$primaryKeys`: *empty*
    - `TDBMService::$toSaveObjects`: *yes*
    - `TDBMService::$objectStorage`: *no*
- `TDBMObjectStateEnum::STATE_LOADED`: when an object has been loaded from `TDBMService` and is not modified.
  This happens after calling the `TDBMService->getObject` for instance.
      - `AbstractTDBMObject::$primaryKeys`: *set*
      - `TDBMService::$toSaveObjects`: *no*
      - `TDBMService::$objectStorage`: *yes*
- `TDBMObjectStateEnum::STATE_DIRTY`: when an object has been loaded from `TDBMService` and is modified.
  This happens after calling the `TDBMService->getObject` for instance, then calling a setter on the bean.
      - `AbstractTDBMObject::$primaryKeys`: *set*
      - `TDBMService::$toSaveObjects`: *yes*
      - `TDBMService::$objectStorage`: *yes*
- `TDBMObjectStateEnum::NOT_LOADED`: when an object has been lazy-loaded from `TDBMService` and no getter has been
  called on it.
      - `AbstractTDBMObject::$primaryKeys`: *set*
      - `TDBMService::$toSaveObjects`: *no*
      - `TDBMService::$objectStorage`: *yes*
- `TDBMObjectStateEnum::DELETED`: when an object has been deleted using `TDBMService->delete`.
      - `AbstractTDBMObject::$primaryKeys`: it depends on original state (before call to delete)
      - `TDBMService::$toSaveObjects`: *no*
      - `TDBMService::$objectStorage`: *no*
