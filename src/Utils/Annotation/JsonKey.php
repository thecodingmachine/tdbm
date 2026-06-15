<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils\Annotation;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * @Annotation
 */
final class JsonKey
{
    /**
     * @Required()
     * @var string
     */
    public $key;
}
