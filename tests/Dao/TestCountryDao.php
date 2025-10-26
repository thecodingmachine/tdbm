<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Dao;

use Porpaginas\Result;
use TheCodingMachine\TDBM\Test\Dao\Bean\CountryBean;
use TheCodingMachine\TDBM\Test\Dao\Generated\CountryBaseDao;

/**
 * The CountryDao class will maintain the persistence of CountryBean class into the country table.
 */
class TestCountryDao extends CountryBaseDao
{
    /**
     * @return CountryBean[]|Result
     */
    public function getCountriesByUserCount()
    {
        $sql = <<<SQL
SELECT country.*
FROM country
LEFT JOIN users ON users.country_id = country.id
GROUP BY country.id, country.label
ORDER BY COUNT(users.id) DESC
SQL;

        return $this->findFromRawSql($sql);
    }

    /**
     * @return CountryBean[]|Result
     */
    public function getCountriesUsingUnion()
    {
        $sql = <<<SQL
SELECT country.*
FROM country
WHERE country.id = 1
UNION
SELECT country.*
FROM country
WHERE country.id = 2
SQL;

        return $this->findFromRawSql($sql);
    }

    /**
     * @return CountryBean[]|Result
     */
    public function getCountriesUsingSimpleQuery()
    {
        $sql = <<<SQL
SELECT country.*
FROM country
WHERE country.id = 1
SQL;

        return $this->findFromRawSql($sql);
    }

    /**
     * @return CountryBean[]|Result
     */
    public function getCountriesUsingDistinctQuery()
    {
        // Note: there are many users whose country is ID 2 (UK).
        // But the distinct should return only one country (including the count() call)
        $sql = <<<SQL
SELECT DISTINCT country.*
FROM country
LEFT JOIN users ON users.country_id = country.id 
WHERE country_id=2
SQL;

        return $this->findFromRawSql($sql);
    }

    /**
     * @return CountryBean[]|Result
     */
    public function findByIds(array $ids)
    {
        return $this->find('id IN (:ids)', ['ids' => $ids]);
    }
}
