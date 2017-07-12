<?php


namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Index;

/**
 * An abstract class offering a common implementation for most names of the NamingStrategyInterface.
 * It assumes getters and setters will start with get... and set..., and so on.
 */
abstract class AbstractNamingStrategy implements NamingStrategyInterface
{
    abstract protected function getUpperCamelCaseName(AbstractBeanPropertyDescriptor $property): string;

    protected function getLowerCamelCaseName(AbstractBeanPropertyDescriptor $property): string
    {
        return lcfirst($this->getUpperCamelCaseName($property));
    }

    /**
     * Returns the getter name generated for the property passed in parameter.
     *
     * @param AbstractBeanPropertyDescriptor $property
     * @return string
     */
    public function getGetterName(AbstractBeanPropertyDescriptor $property): string
    {
        return 'get'.$this->getUpperCamelCaseName($property);
    }

    /**
     * Returns the setter name generated for the property passed in parameter.
     *
     * @param AbstractBeanPropertyDescriptor $property
     * @return string
     */
    public function getSetterName(AbstractBeanPropertyDescriptor $property): string
    {
        return 'set'.$this->getUpperCamelCaseName($property);
    }

    /**
     * Returns the variable name used in the setter generated for the property passed in parameter.
     *
     * @param AbstractBeanPropertyDescriptor $property
     * @return string
     */
    public function getVariableName(AbstractBeanPropertyDescriptor $property): string
    {
        return '$'.$this->getLowerCamelCaseName($property);
    }

    /**
     * Returns the label of the JSON property for the property passed in parameter.
     *
     * @param AbstractBeanPropertyDescriptor $property
     * @return string
     */
    public function getJsonProperty(AbstractBeanPropertyDescriptor $property): string
    {
        return $this->getLowerCamelCaseName($property);
    }

    /**
     * Returns the name of the find method attached to an index.
     *
     * @param AbstractBeanPropertyDescriptor[] $elements The list of properties in the index.
     * @return string
     */
    public function getFindByIndexMethodName(Index $index, array $elements): string
    {
        $methodNameComponent = array_map([$this, 'getUpperCamelCaseName'], $elements);

        if ($index->isUnique()) {
            return 'findOneBy'.implode('And', $methodNameComponent);
        } else {
            return 'findBy' . implode('And', $methodNameComponent);
        }
    }
}
