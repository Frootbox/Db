<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;

class Less extends AbstractCondition
{
    /**
     *
     */
    public function toString(): string
    {
        return  $this->column . ' < :paramCondLOE_1';
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return [
            new Parameter($this->column, ':paramCondLOE_1', $this->input)
        ];
    }
}
