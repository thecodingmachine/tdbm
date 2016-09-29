<?php

namespace Mouf\Database\TDBM\QueryFactory;

/**
 * Classes implementing this interface can generate SQL and SQL count queries to be used by result iterators.
 */
interface QueryFactory
{
    public function getMagicSql() : string;

    public function getMagicSqlCount() : string;

    public function getColumnDescriptors() : array;
}
