<?php


namespace TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad;


use Mouf\Database\MagicQuery;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\Query\PartialQuery;

interface PartialQueryFactory
{
    /**
     * Generates a SQL query to be used as a sub-query.
     * @param array<string, mixed> $parameters
     */
    public function getPartialQuery(StorageNode $storageNode, MagicQuery $magicQuery, array $parameters) : PartialQuery;
}
