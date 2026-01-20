<?php


namespace TheCodingMachine\TDBM\Utils\Annotation;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * @Annotation
 */
final class Interface_
{
    /**
     * The fully qualified interface name this bean should implement.
     *
     * @Required()
     * @string
     */
    public $value;
}
