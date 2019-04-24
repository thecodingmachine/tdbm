<?php
declare(strict_types=1);


namespace TheCodingMachine\TDBM\Utils\Annotation;

use function array_values;
use TheCodingMachine\TDBM\TDBMException;

/**
 * Represents a list of annotations in a column comment.
 */
class Annotations
{
    /**
     * @var object[]
     */
    private $annotations;

    /**
     * @param object[] $annotations
     */
    public function __construct(array $annotations)
    {
        $this->annotations = $annotations;
    }

    /**
     * @return object[]
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    /**
     * @param string $annotationType
     * @return object[]
     */
    public function findAnnotations(string $annotationType): array
    {
        return array_values(array_filter($this->annotations, function ($annotation) use ($annotationType) {
            return is_a($annotation, $annotationType);
        }));
    }

    /**
     * @param string $annotationType
     * @return null|object
     * @throws TDBMException
     */
    public function findAnnotation(string $annotationType)
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
