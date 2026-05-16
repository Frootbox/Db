<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;

class Greater extends AbstractCondition
{
    /**
     * Render a greater-than comparison.
     *
     * @return string
     */
    public function toString(): string
    {
        return  $this->column . ' > :paramCondGOE_' . $this->getUid();
    }

    /**
     * Return the bound comparison parameter.
     *
     * @return array<int, Parameter>
     */
    public function getParameters(): array
    {
        return [
            new Parameter($this->column, ':paramCondGOE_' . $this->getUid(), $this->input)
        ];
    }
}
