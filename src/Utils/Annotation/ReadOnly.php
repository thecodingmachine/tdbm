<?php
namespace TheCodingMachine\TDBM\Utils\Annotation;

/**
 * Declares a column as "read-only".
 * Read-only columns cannot be set neither in a setter nor in a constructor argument.
 * They are very useful on generated/computed columns.
 *
 * This annotation can only be used in a database column comment.
 *
 * @Annotation
 */
final class ReadOnly
{
}
