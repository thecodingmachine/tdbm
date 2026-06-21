<?php
namespace TheCodingMachine\TDBM\Utils\Annotation;

/**
 * @Annotation
 */
final class Setter
{
    /**
     * The name of the setter
     *
     * @var string
     */
    public $name;

    /**
     * If true, the setter will be "protected". Otherwise, setter is "public".
     *
     * @var bool
     */
    public $hidden = false;
}
