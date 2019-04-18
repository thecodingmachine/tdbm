<?php

namespace TheCodingMachine\TDBM\Utils\Annotation;

/**
 * @Annotation
 */
final class JsonFormat
{
    /**
     * The datetime format
     * @see date
     * @var string
     */
    public $datetime = 'c';
    /**
     * The number of decimals
     * @see number_format
     * @var int
     */
    public $decimals = null;
    /**
     * The decimal point
     * @see number_format
     * @var string
     */
    public $point = null;
    /**
     * The thousands separator
     * @see number_format
     * @var string
     */
    public $separator = null;
    /**
     * The suffix to append after a number
     * @var string
     */
    public $unit = null;
    /**
     * The property to get from an object.
     * @var string
     */
    public $property;
    /**
     * The method to call from an object.
     * @var string
     */
    public $method;
}
