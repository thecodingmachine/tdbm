<?php

namespace TheCodingMachine\TDBM\Utils\Annotation;

/**
 * Makes the inverse relationship of a foreign key protected.
 *
 * For instance, if this annotation is put on a column users.country_id,
 * the getUsers method of the Country bean will be protected.
 *
 * This annotation can only be used in a database column comment.
 *
 * @Annotation
 */
final class ProtectedOneToMany
{
}
