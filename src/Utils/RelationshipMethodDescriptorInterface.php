<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

use Laminas\Code\Generator\MethodGenerator;

interface RelationshipMethodDescriptorInterface extends MethodDescriptorInterface
{
    /**
     * Returns the name of the method to be generated.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Returns the name of the class that will be returned by the getter (short name).
     *
     * @return string
     */
    public function getBeanClassName(): string;

    /**
     * Requests the use of an alternative name for this method.
     */
    public function useAlternativeName(): void;

    /**
     * Returns the code of the method.
     *
     * @return MethodGenerator[]
     */
    public function getCode(): array;

    /**
     * Returns an array of classes that needs a "use" for this method.
     *
     * @return string[]
     */
    public function getUsedClasses(): array;

    /**
     * Returns the code to past in jsonSerialize.
     *
     * @return string
     */
    public function getJsonSerializeCode(): string;
}
