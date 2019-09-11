<?php


namespace TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Mouf\Database\MagicQuery;
use TheCodingMachine\TDBM\QueryFactory\SmartEagerLoad\StorageNode;
use TheCodingMachine\TDBM\TDBMException;
use function implode;
use function strpos;

class StaticPartialQuery implements PartialQuery
{
    /**
     * @var string
     */
    private $queryFrom;
    /**
     * @var string[]
     */
    private $mainTables;
    /**
     * @var StorageNode
     */
    private $storageNode;
    /**
     * @var array<string, mixed>
     */
    private $parameters;
    /**
     * @var MagicQuery
     */
    private $magicQuery;
    /**
     * @var string
     */
    private $magicFrom;

    /**
     * @param array<string, mixed> $parameters
     * @param string[] $mainTables
     */
    public function __construct(string $queryFrom, array $parameters, array $mainTables, StorageNode $storageNode, MagicQuery $magicQuery)
    {
        $this->queryFrom = $queryFrom;
        $this->mainTables = $mainTables;
        $this->storageNode = $storageNode;
        $this->parameters = $parameters;
        $this->magicQuery = $magicQuery;
    }

    /**
     * Returns the SQL of the query, starting at the FROM keyword.
     */
    public function getQueryFrom(): string
    {
        if ($this->magicFrom === null) {
            // FIXME: we need to use buildPreparedStatement for better performances here.
            $sql = 'SELECT ';
            $mysqlPlatform = new MySqlPlatform();
            $tables = [];
            foreach ($this->mainTables as $table) {
                $tables[] = $mysqlPlatform->quoteIdentifier($table).'.*';
            }
            $sql .= implode(', ', $tables);
            $sql .= ' '.$this->queryFrom;

            $this->magicQuery->setOutputDialect($mysqlPlatform);
            $sql = $this->magicQuery->build($sql, $this->parameters);
            $this->magicQuery->setOutputDialect(null);
            $fromIndex = strpos($sql, 'FROM');
            if ($fromIndex === false) {
                throw new TDBMException('Expected smart eager loader query to contain a "FROM"'); // @codeCoverageIgnore
            }
            $this->magicFrom = substr($sql, $fromIndex);
        }
        return $this->magicFrom;
    }

    /**
     * Returns a key representing the "path" to this query. This is meant to be used as a cache key.
     */
    public function getKey(): string
    {
        return '';
    }

    /**
     * Registers a dataloader for this query, if needed.
     */
    public function registerDataLoader(Connection $connection): void
    {
        throw new TDBMException('Cannot register a dataloader for root query');
    }

    /**
     * Returns the object in charge of storing the dataloader associated to this query.
     */
    public function getStorageNode(): StorageNode
    {
        return $this->storageNode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getMagicQuery(): MagicQuery
    {
        return $this->magicQuery;
    }
}
