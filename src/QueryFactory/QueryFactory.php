<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\QueryFactory;

use TheCodingMachine\TDBM\UncheckedOrderBy;

/**
 * Classes implementing this interface can generate SQL and SQL count queries to be used by result iterators.
 */
interface QueryFactory
{
    /**
     * Sets the ORDER BY directive executed in SQL.
     *
     * For instance:
     *
     *  $queryFactory->sort('label ASC, status DESC');
     *
     * **Important:** TDBM does its best to protect you from SQL injection. In particular, it will only allow column names in the "ORDER BY" clause. This means you are safe to pass input from the user directly in the ORDER BY parameter.
     * If you want to pass an expression to the ORDER BY clause, you will need to tell TDBM to stop checking for SQL injections. You do this by passing a `UncheckedOrderBy` object as a parameter:
     *
     *  $queryFactory->sort(new UncheckedOrderBy('RAND()'))
     *
     * @param string|UncheckedOrderBy|null $orderBy
     */
    public function sort($orderBy);

    public function getMagicSql() : string;

    public function getMagicSqlCount() : string;

    public function getColumnDescriptors() : array;
}
