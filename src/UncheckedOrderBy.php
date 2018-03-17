<?php

namespace TheCodingMachine\TDBM;

/**
 * Use this object to inject any SQL string in an order by clause in $dao->find methods.
 *
 * By default, TDBM is conservative and prevents an ORDERBY clause to be anything other than a sort on columns.
 * This is done to prevent SQL injections.
 *
 * If you need to order on an expression, you can wrap your ORDERBY clause in this class.
 *
 * For instance:
 *
 * $this->find(null, null, new UncheckedOrderBy('RAND()'));
 *
 * Note: you understand that arguments passed inside the `UncheckedOrderBy` constructor are NOT protected and
 * can be used for an SQL injection based attack. Therefore, you understand that you MUST NOT put input from the user
 * in this constructor.
 */
class UncheckedOrderBy
{
    /**
     * @var string
     */
    private $orderBy;

    public function __construct(string $orderBy)
    {
        $this->orderBy = $orderBy;
    }

    public function getOrderBy() : string
    {
        return $this->orderBy;
    }
}
