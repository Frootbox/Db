<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;


class InArray extends AbstractCondition
{
    /**
     * Render an IN (...) comparison.
     *
     * @return string
     */
    public function toString(): string
    {
        return  $this->column . ' IN ("' . implode('", "', $this->input) . '")';
    }

    /**
     * Return bound parameters for the condition.
     *
     * @return array<int, Parameter>
     */
    public function getParameters ( ): array {

        return [ ];
    }
}
