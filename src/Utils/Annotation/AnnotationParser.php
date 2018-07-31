<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils\Annotation;

use Doctrine\Common\Annotations\DocParser;

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
     * @param string[] $annotations An array mapping the annotation name to the fully qualified class name
     */
    public function __construct(array $annotations)
    {
        $this->docParser = new DocParser();
        $this->docParser->setImports(array_change_key_case($annotations, \CASE_LOWER));
    }

    /**
     * @param array<string,string> $additionalAnnotations An array associating the name of the annotation in DB comments to the name of a fully qualified Doctrine annotation class
     */
    public static function buildWithDefaultAnnotations(array $additionalAnnotations): self
    {
        $defaultAnnotations = [
            'UUID' => UUID::class,
            'Autoincrement' => Autoincrement::class
        ];
        $annotations = $defaultAnnotations + $additionalAnnotations;
        return new self($annotations);
    }

    /**
     * Parses the doc comment and initializes all the values of interest.
     *
     */
    public function parse(string $comment, string $context): Annotations
    {
        // compatibility with UUID annotation from TDBM 5.0
        $comment = \str_replace(['@UUID v1', '@UUID v4'], ['@UUID("v1")', '@UUID("v4")'], $comment);

        // TODO: add context (table name...)
        $annotations = $this->docParser->parse($comment, $context);

        return new Annotations($annotations);
    }
}
