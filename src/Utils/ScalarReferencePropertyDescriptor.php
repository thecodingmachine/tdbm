<?php


namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use TheCodingMachine\TDBM\Utils\Annotation\AnnotationParser;

/**
 * This class represents a reference to a AbstractBeanPropertyDescriptor
 */
class ScalarReferencePropertyDescriptor extends ScalarBeanPropertyDescriptor
{
    /** @var AbstractBeanPropertyDescriptor */
    private $referencedPropertyDescriptor;

    public function __construct(
        Table $table,
        Column $column,
        NamingStrategyInterface $namingStrategy,
        AnnotationParser $annotationParser,
        AbstractBeanPropertyDescriptor $referencedPropertyDescriptor
    )
    {
        parent::__construct($table, $column, $namingStrategy, $annotationParser);
        $this->referencedPropertyDescriptor = $referencedPropertyDescriptor;
    }

    /**
     * @return AbstractBeanPropertyDescriptor
     */
    public function getReferencedPropertyDescriptor(): AbstractBeanPropertyDescriptor
    {
        return $this->referencedPropertyDescriptor;
    }
}
