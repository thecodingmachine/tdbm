<?php


namespace TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad;

use Doctrine\DBAL\Connection;
use TheCodingMachine\TDBM\TDBMException;

class OneToManyDataLoader
{
    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var string
     */
    private $sql;
    /**
     * @var string
     */
    private $fkColumn;
    /**
     * @var array<string, array<int, array<string, mixed>>> Array of rows, indexed by foreign key.
     */
    private $data;

    public function __construct(Connection $connection, string $sql, string $fkColumn)
    {
        $this->connection = $connection;
        $this->sql = $sql;
        $this->fkColumn = $fkColumn;
    }

    /**
     * @return array<string, array<string, mixed>> Rows, indexed by ID.
     */
    private function load(): array
    {
        $results = $this->connection->fetchAll($this->sql);

        $data = [];
        foreach ($results as $row) {
            $data[$row[$this->fkColumn]][] = $row;
        }

        return $data;
    }

    /**
     * Returns the DB row with the given ID.
     * Loads all rows if necessary.
     * Throws an exception if nothing found.
     *
     * @param string $id
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        if ($this->data === null) {
            $this->data = $this->load();
        }

        if (!isset($this->data[$id])) {
            return [];
        }
        return $this->data[$id];
    }
}
