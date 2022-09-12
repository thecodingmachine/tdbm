<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;

/**
 * This class represents a reference to a AbstractBeanPropertyDescriptor used in an inheritance schema
 */
class InheritanceReferencePropertyDescriptor extends ScalarBeanPropertyDescriptor
{
    /** @var AbstractBeanPropertyDescriptor */
    private $referencedPropertyDescriptor;

    public function __construct(
        Table $table,
        Column $column,
        NamingStrategyInterface $namingStrategy,
        AnnotationParser $annotationParser,
        AbstractBeanPropertyDescriptor $referencedPropertyDescriptor
    ) {
        parent::__construct($table, $column, $namingStrategy, $annotationParser);
        $this->referencedPropertyDescriptor = $referencedPropertyDescriptor;
    }

    /**
     * @return ScalarBeanPropertyDescriptor|ObjectBeanPropertyDescriptor
     */
    public function getNonScalarReferencedPropertyDescriptor(): AbstractBeanPropertyDescriptor
    {
        if ($this->referencedPropertyDescriptor instanceof InheritanceReferencePropertyDescriptor) {
            return $this->referencedPropertyDescriptor->getNonScalarReferencedPropertyDescriptor();
        }
        assert($this->referencedPropertyDescriptor instanceof ScalarBeanPropertyDescriptor || $this->referencedPropertyDescriptor instanceof ObjectBeanPropertyDescriptor);
        return $this->referencedPropertyDescriptor;
    }

    public function getJsonSerializeCode(): string
    {
        return $this->getNonScalarReferencedPropertyDescriptor()->getJsonSerializeCode();
    }
}
