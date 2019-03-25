<?php


namespace TheCodingMachine\TDBM\Utils\Annotation;

/**
 * @Annotation
 * @Attributes({
 *   @Attribute("names", type = "array<string>"),
 * })
 */
final class Interfaces
{
    /**
     * The list of PHP interfaces that are implemented by the Bean.
     *
     * @var array<string>
     */
    private $names;

    /**
     * @param array<string, mixed> $values
     *
     * @throws \BadMethodCallException
     */
    public function __construct(array $values)
    {
        if (!isset($values['value']) && !isset($values['names'])) {
            throw new \BadMethodCallException('The @Interfaces annotation must be passed a list of interfaces to implement. For instance: \'@Interfaces(["Foo\\BarInterface", "Foo\\BazInterface"])\'');
        }
        $this->names = $values['value'] ?? $values['names'];
    }

    /**
     * @return array
     */
    public function getNames(): array
    {
        return $this->names;
    }
}
