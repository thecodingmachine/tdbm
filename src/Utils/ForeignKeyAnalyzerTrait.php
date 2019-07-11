<?php


namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use TheCodingMachine\TDBM\Utils\Annotation\Annotations;

trait ForeignKeyAnalyzerTrait
{
    /**
     * @var Annotations[]
     */
    private $annotations;
    /**
     * @var Column[]
     */
    private $localColumns;

    /**
     * @return Column[]
     */
    private function getLocalColumns(): array
    {
        if ($this->localColumns === null) {
            $localColumnNames = $this->foreignKey->getUnquotedLocalColumns();

            $this->localColumns = array_map([$this->foreignKey->getLocalTable(), 'getColumn'], $localColumnNames);
        }
        return $this->localColumns;
    }

    /**
     * @return Annotations[]
     */
    private function getAnnotations(): array
    {
        if ($this->annotations === null) {
            $this->annotations = [];

            // Are all columns nullable?
            foreach ($this->getLocalColumns() as $column) {
                $this->annotations[] = $this->annotationParser->getColumnAnnotations($column, $this->foreignKey->getLocalTable());
            }
        }
        return $this->annotations;
    }
}
