<?php


namespace TheCodingMachine\TDBM\Utils\Annotation;

/**
 * @Annotation
 */
final class UUID
{
    /**
     * @Enum({"v1", "v4"})
     *
     * @var string
     */
    public $value;
}
