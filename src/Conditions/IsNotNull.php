<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;

class IsNotNull extends AbstractCondition
{
    /**
     * Render an IS NOT NULL comparison.
     *
     * @return string
     */
    public function toString(): string
    {
        return  $this->column . ' IS NOT NULL';
    }

    /**
     * Return bound parameters for the condition.
     *
     * @return array<int, Parameter>
     */
    public function getParameters(): array
    {
        return [
     
        ];
    }
}
