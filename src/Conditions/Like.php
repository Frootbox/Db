<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;


class Like extends AbstractCondition
{
    /**
     * Render a LIKE comparison.
     *
     * @return string
     */
    public function toString(): string
    {
        return  $this->column . ' LIKE :paramCondLike_1';
    }

    /**
     * Return the bound LIKE parameter.
     *
     * @return array<int, Parameter>
     */
    public function getParameters(): array
    {
        return [
            new Parameter($this->column, ':paramCondLike_1', $this->input)
        ];
    }
}
