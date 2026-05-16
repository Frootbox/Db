<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 */

namespace Frootbox\Db\Conditions;


class Equal extends AbstractCondition
{
    /**
     * Render a column equality comparison.
     *
     * @return string
     */
    public function toString(): string
    {
        return  $this->column . ' = :' . $this->getUid();
    }

    /**
     * Return the bound equality parameter.
     *
     * @return array<int, Parameter>
     */
    public function getParameters(): array
    {
        return [
            new Parameter($this->column, ':' . $this->getUid(), $this->input)
        ];
    }
}
