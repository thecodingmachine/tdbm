<?php
declare(strict_types=1);


namespace TheCodingMachine\TDBM\Utils\Annotation;

use TheCodingMachine\TDBM\TDBMException;

/**
 * Represents a list of annotations in a column comment.
 */
class Annotations
{
    /**
     * @var array|Annotation[]
     */
    private $annotations;

    /**
     * @param Annotation[] $annotations
     */
    public function __construct(array $annotations)
    {
        $this->annotations = $annotations;
    }

    /**
     * @return Annotation[]
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    /**
     * @param string $annotationType
     * @return Annotation[]
     */
    public function findAnnotations(string $annotationType): array
    {
        return array_filter($this->annotations, function (Annotation $annotation) use ($annotationType) {
            return $annotation->getAnnotationType() === $annotationType;
        });
    }

    public function findAnnotation(string $annotationType): ?Annotation
    {
        $annotations = $this->findAnnotations($annotationType);

        $annotationsCount = count($annotations);
        if ($annotationsCount === 1) {
            return $annotations[0];
        } elseif ($annotationsCount === 0) {
            return null;
        } else {
            throw new TDBMException(sprintf('Unexpected column annotation. Found %d annotations of type %s', $annotationsCount, $annotationType));
        }
    }
}
