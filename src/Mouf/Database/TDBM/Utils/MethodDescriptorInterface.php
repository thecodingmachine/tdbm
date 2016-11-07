<?php

namespace Mouf\Database\TDBM\Utils;

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
    public function useAlternativeName();

    /**
     * Returns the code of the method.
     *
     * @return string
     */
    public function getCode() : string;

    /**
     * Returns an array of classes that needs a "use" for this method.
     *
     * @return string[]
     */
    public function getUsedClasses() : array;

    /**
     * Returns the code to past in jsonSerialize.
     *
     * @return string
     */
    public function getJsonSerializeCode() : string;
}
