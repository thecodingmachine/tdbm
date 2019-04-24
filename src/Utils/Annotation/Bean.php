<?php
namespace TheCodingMachine\TDBM\Utils\Annotation;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * @Annotation
 */
final class Bean
{
    /**
     * The name of the bean in its singular form (does not contain prefix/suffix)
     *
     * @Required()
     * @var string
     */
    public $name;
}
