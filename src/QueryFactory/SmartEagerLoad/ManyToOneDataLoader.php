<?php


namespace TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad;

use Doctrine\DBAL\Connection;
use TheCodingMachine\TDBM\TDBMException;

class ManyToOneDataLoader
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
    private $idColumn;
    /**
     * @var array<string, array<string, mixed>> Rows, indexed by ID.
     */
    private $data;

    public function __construct(Connection $connection, string $sql, string $idColumn)
    {
        $this->connection = $connection;
        $this->sql = $sql;
        $this->idColumn = $idColumn;
    }

    /**
     * @return array<string, array<string, mixed>> Rows, indexed by ID.
     */
    private function load(): array
    {
        $results = $this->connection->fetchAll($this->sql);
        $results = array_column($results, null, $this->idColumn);

        return $results;
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
            throw new TDBMException('The loaded dataset does not contain row with ID "'.$id.'"');
        }
        return $this->data[$id];
    }
}
