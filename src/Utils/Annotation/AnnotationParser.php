<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils\Annotation;

/**
 * Parses annotations in database columns.
 */
class AnnotationParser
{
    /**
     * Parses the doc comment and initilizes all the values of interest.
     *
     */
    public function parse(string $comment): Annotations
    {
        $lines = explode("\n", $comment);
        $lines = array_map(function (string $line) {
            return trim($line, " \r\t");
        }, $lines);

        $annotations = [];

        // Is the line an annotation? Let's test this with a regexp.
        foreach ($lines as $line) {
            if (preg_match("/^[@][a-zA-Z]/", $line) === 1) {
                $annotations[] = $this->parseAnnotation($line);
            }
        }

        return new Annotations($annotations);
    }

    /**
     * Parses an annotation line and returns a Annotation object.
     */
    private function parseAnnotation(string $line): Annotation
    {
        // Let's get the annotation text
        preg_match("/^[@]([a-zA-Z][a-zA-Z0-9]*)(.*)/", $line, $values);

        $annotationClass = $values[1];
        $annotationParams = trim(isset($values[2])?$values[2]:null);

        return new Annotation($annotationClass, $annotationParams);
    }
}
