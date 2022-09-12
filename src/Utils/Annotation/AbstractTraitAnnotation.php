<?php

namespace TheCodingMachine\TDBM\Utils\Annotation;

use TheCodingMachine\TDBM\TDBMException;

use function explode;
use function preg_match;
use function strpos;

abstract class AbstractTraitAnnotation
{
    /**
     * The PHP trait that is used by the Bean.
     *
     * @var string
     */
    private $name;

    /**
     * @var array<string, string>
     */
    private $insteadOf = [];

    /**
     * @var array<string, string>
     */
    private $as = [];

    /**
     * @param array<string, mixed> $values
     *
     * @throws \BadMethodCallException
     */
    public function __construct(array $values)
    {
        if (!isset($values['value']) && !isset($values['name'])) {
            throw new \BadMethodCallException('The @AddTrait annotation must be passed a trait to use. For instance: \'@AddTrait("Foo\\BarTrait")\'');
        }
        $this->name = $values['value'] ?? $values['name'];

        $modifiers = $values['modifiers'] ?? [];

        foreach ($modifiers as $modifier) {
            if (preg_match('/(.*?)\s*insteadof\s(.*)/', $modifier, $matches) === 1) {
                $this->insteadOf[$matches[1]] = $matches[2];
            } elseif (preg_match('/(.*?)\s*as\s(.*)/', $modifier, $matches) === 1) {
                $this->as[$matches[1]] = $matches[2];
            } else {
                throw new TDBMException('In annotation @AddTrait, the modifiers parameter must be passed either a "insteadof" or a "as" clause. For instance: \'@AddTrait("Foo\\A", modifiers={"A::foo insteadof B", "A::bar as baz"})\'');
            }
        }
    }

    public function getName(): string
    {
        return '\\'.ltrim($this->name, '\\');
    }

    /**
     * A list of "insteadof" clauses. Key: method name to be use, Value: trait to replace
     *
     * @return array<string,string>
     */
    public function getInsteadOf(): array
    {
        return $this->insteadOf;
    }

    /**
     * A list of "as" clauses. Key: method name to be renamed, Value: method name
     *
     * @return array<string,string>
     */
    public function getAs(): array
    {
        return $this->as;
    }
}
