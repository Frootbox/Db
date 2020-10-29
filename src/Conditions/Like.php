<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;


class Like extends AbstractCondition
{
    /**
     *
     */
    public function toString(): string
    {
        return  $this->column . ' LIKE :paramCondLike_1';
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return [
            new Parameter($this->column, ':paramCondLike_1', $this->input)
        ];
    }
}