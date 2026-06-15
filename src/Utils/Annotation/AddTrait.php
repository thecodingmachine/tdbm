<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils\Annotation;

/**
 * @Annotation
 * @Attributes({
 *   @Attribute("name", type = "string"),
 *   @Attribute("modifiers", type = "array<string>"),
 * })
 */
final class AddTrait extends AbstractTraitAnnotation {}
