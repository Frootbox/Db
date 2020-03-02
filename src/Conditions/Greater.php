<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;

class Greater extends AbstractCondition
{
    /**
     *
     */
    public function toString(): string
    {
        return  $this->column . ' > :paramCondGOE_1';
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return [
            new Parameter($this->column, ':paramCondGOE_1', $this->input)
        ];
    }
}
