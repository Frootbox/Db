<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 */

namespace Frootbox\Db\Conditions;


class Equal extends AbstractCondition
{
    /**
     * @return string
     */
    public function toString(): string
    {
        return  $this->column . ' = :' . $this->getUid();
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return [
            new Parameter($this->column, ':' . $this->getUid(), $this->input)
        ];
    }
}
