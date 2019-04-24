<?php


namespace TheCodingMachine\TDBM\Utils\Annotation;

/**
 * @Annotation
 * @Attributes({
 *   @Attribute("name", type = "string"),
 * })
 */
final class AddInterfaceOnDao
{
    /**
     * The PHP interface that is implemented by the Dao.
     *
     * @var string
     */
    private $name;

    /**
     * @param array<string, mixed> $values
     *
     * @throws \BadMethodCallException
     */
    public function __construct(array $values)
    {
        if (!isset($values['value']) && !isset($values['name'])) {
            throw new \BadMethodCallException('The @AddInterfaceOnDao annotation must be passed an interface to implement. For instance: \'@AddInterfaceOnDao("Foo\\BarInterface")\'');
        }
        $this->name = $values['value'] ?? $values['name'];
    }

    public function getName(): string
    {
        return $this->name;
    }
}
