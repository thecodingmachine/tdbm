<?php


namespace TheCodingMachine\TDBM\Utils\Annotation;

/**
 * Represents an annotation in a column comment.
 */
class Annotation
{
    /**
     * @var string
     */
    private $annotationType;
    /**
     * @var string
     */
    private $annotationComment;

    /**
     * @param string $annotationType The type of the annotation (the string after the @)
     * @param string $annotationComment Anything to the right of the annotation.
     */
    public function __construct(string $annotationType, string $annotationComment)
    {
        $this->annotationType = $annotationType;
        $this->annotationComment = $annotationComment;
    }

    /**
     * Return the type of the annotation (the string after the @)
     *
     * @return string
     */
    public function getAnnotationType(): string
    {
        return $this->annotationType;
    }

    /**
     * Return anything to the right of the annotation.
     *
     * @return string
     */
    public function getAnnotationComment(): string
    {
        return $this->annotationComment;
    }
}
