<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils\Annotation;

use function array_map;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\DocParser;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use function explode;
use function implode;

/**
 * Parses annotations in database columns.
 */
class AnnotationParser
{
    /**
     * @var DocParser
     */
    private $docParser;

    /**
     * AnnotationParser constructor.
     * @param array<string, class-string> $annotations An array mapping the annotation name to the fully qualified class name
     */
    public function __construct(array $annotations)
    {
        $this->docParser = new DocParser();
        $this->docParser->setImports(array_change_key_case($annotations, \CASE_LOWER));
    }

    /**
     * @param array<string,class-string> $additionalAnnotations An array associating the name of the annotation in DB comments to the name of a fully qualified Doctrine annotation class
     */
    public static function buildWithDefaultAnnotations(array $additionalAnnotations): self
    {
        $defaultAnnotations = [
            'UUID' => UUID::class,
            'Autoincrement' => Autoincrement::class,
            'Bean' => Bean::class,
            'ProtectedGetter' => ProtectedGetter::class,
            'ProtectedSetter' => ProtectedSetter::class,
            'ProtectedOneToMany' => ProtectedOneToMany::class,
            'ReadOnly' => ReadOnly::class,
            'JsonKey' => JsonKey::class,
            'JsonIgnore' => JsonIgnore::class,
            'JsonInclude' => JsonInclude::class,
            'JsonRecursive' => JsonRecursive::class,
            'JsonCollection' => JsonCollection::class,
            'JsonFormat' => JsonFormat::class,
            'AddInterface' => AddInterface::class,
            'AddInterfaceOnDao' => AddInterfaceOnDao::class,
            'AddTrait' => AddTrait::class,
            'AddTraitOnDao' => AddTraitOnDao::class,
        ];
        $annotations = array_merge($defaultAnnotations, $additionalAnnotations);

        return new self($annotations);
    }

    /**
     * Parses the doc comment and initializes all the annotations.
     */
    private function parse(string $comment, string $context): Annotations
    {
        AnnotationRegistry::registerUniqueLoader('class_exists');

        // compatibility with UUID annotation from TDBM 5.0
        $comment = \str_replace(['@UUID v1', '@UUID v4'], ['@UUID("v1")', '@UUID("v4")'], $comment);

        // Let's add * in front of the line (otherwise, parsing is failing)
        $lines = explode("\n", $comment);
        $lines = array_map(function (string $line) {
            return '* '.$line;
        }, $lines);
        $comment = implode("\n", $lines);

        $annotations = $this->docParser->parse($comment, $context);

        return new Annotations($annotations);
    }

    public function getTableAnnotations(Table $table): Annotations
    {
        $options = $table->getOptions();
        if (isset($options['comment'])) {
            return $this->parse($options['comment'], ' comment in table '.$table->getName());
        }
        return new Annotations([]);
    }

    public function getColumnAnnotations(Column $column, Table $table): Annotations
    {
        $comment = $column->getComment();
        if ($comment === null) {
            return new Annotations([]);
        }
        return $this->parse($comment, sprintf('comment of column %s in table %s', $column->getName(), $table->getName()));
    }
}
