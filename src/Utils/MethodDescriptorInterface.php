<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils;

interface MethodDescriptorInterface
{
    /**
     * Returns the name of the method to be generated.
     *
     * @return string
     */
    public function getName() : string;

    /**
     * Requests the use of an alternative name for this method.
     */
    public function useAlternativeName(): void;
}
