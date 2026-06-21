<?php
namespace TheCodingMachine\TDBM\Utils\Annotation;

/**
 * @Annotation
 */
final class Getter
{
    /**
     * The name of the getter
     *
     * @var string
     */
    public $name;

    /**
     * If true, the getter will be "protected". Otherwise, getter is "public".
     *
     * @var bool
     */
    public $hidden = false;
}
