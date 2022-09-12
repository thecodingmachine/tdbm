<?php

namespace TheCodingMachine\TDBM\Utils\Annotation;

/**
 * Makes the getter of the column protected.
 *
 * This annotation can only be used in a database column comment.
 * When used, the column will also be removed from the default JSONSerialize
 *
 * @Annotation
 */
final class ProtectedGetter
{
}
