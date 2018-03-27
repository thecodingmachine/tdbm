<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM;

use Doctrine\Common\Cache\Cache;
use PHPSQLParser\PHPSQLParser;

/**
 * Class in charge of analyzing order by clauses.
 *
 * Analyzing those clauses are important because an order by clause must be pushed into the select clause too.
 */
class OrderByAnalyzer
{
    /**
     * The content of the cache variable.
     *
     * @var Cache
     */
    private $cache;

    /**
     * @var string|null
     */
    private $cachePrefix;

    /**
     * OrderByAnalyzer constructor.
     *
     * @param Cache       $cache
     * @param string|null $cachePrefix
     */
    public function __construct(Cache $cache, ?string $cachePrefix = null)
    {
        $this->cache = $cache;
        $this->cachePrefix = $cachePrefix;
    }

    /**
     * Returns an array for each sorted "column" in the form:.
     *
     * [
     *      [
     *          'type' => 'colref',
     *          'table' => null,
     *          'column' => 'a',
     *          'direction' => 'ASC'
     *      ],
     *      [
     *          'type' => 'expr',
     *          'expr' => 'RAND()',
     *          'direction' => 'DESC'
     *      ]
     * ]
     *
     * @param string $orderBy
     *
     * @return array
     */
    public function analyzeOrderBy(string $orderBy) : array
    {
        $key = $this->cachePrefix.'_order_by_analysis_'.$orderBy;
        $results = $this->cache->fetch($key);
        if ($results !== false) {
            return $results;
        }
        $results = $this->analyzeOrderByNoCache($orderBy);
        $this->cache->save($key, $results);

        return $results;
    }

    private function analyzeOrderByNoCache(string $orderBy) : array
    {
        $sqlParser = new PHPSQLParser();
        $sql = 'SELECT 1 FROM a ORDER BY '.$orderBy;
        $parsed = $sqlParser->parse($sql, true);

        $results = [];

        for ($i = 0, $count = count($parsed['ORDER']); $i < $count; ++$i) {
            $orderItem = $parsed['ORDER'][$i];
            if ($orderItem['expr_type'] === 'colref') {
                $parts = $orderItem['no_quotes']['parts'];
                $columnName = array_pop($parts);
                if (!empty($parts)) {
                    $tableName = array_pop($parts);
                } else {
                    $tableName = null;
                }

                $results[] = [
                    'type' => 'colref',
                    'table' => $tableName,
                    'column' => $columnName,
                    'direction' => $orderItem['direction'],
                ];
            } else {
                $position = $orderItem['position'];
                if ($i + 1 < $count) {
                    $nextPosition = $parsed['ORDER'][$i + 1]['position'];
                    $str = substr($sql, $position, $nextPosition - $position);
                } else {
                    $str = substr($sql, $position);
                }

                $str = trim($str, " \t\r\n,");

                $results[] = [
                    'type' => 'expr',
                    'expr' => $this->trimDirection($str),
                    'direction' => $orderItem['direction'],
                ];
            }
        }

        return $results;
    }

    /**
     * Trims the ASC/DESC direction at the end of the string.
     *
     * @param string $sql
     *
     * @return string
     */
    private function trimDirection(string $sql) : string
    {
        preg_match('/^(.*)(\s+(DESC|ASC|))*$/Ui', $sql, $matches);

        return $matches[1];
    }
}
