<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;

class IsNull extends AbstractCondition
{
    /**
     * Render an IS NULL comparison.
     *
     * @return string
     */
    public function toString(): string
    {
        return  $this->column . ' IS NULL';
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
