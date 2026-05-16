<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 */

namespace Frootbox\Db\Conditions;

/**
 * Condition that renders a NOT IN (...) comparison.
 */
class NotInArray extends AbstractCondition
{
    /**
     * Render a NOT IN (...) comparison.
     *
     * @return string
     */
    public function toString(): string
    {
        return  $this->column . ' NOT IN ("' . implode('", "', $this->input) . '")';
    }

    /**
     * Return bound parameters for the condition.
     *
     * @return array<int, Parameter>
     */
    public function getParameters(): array
    {
        return [];
    }
}
