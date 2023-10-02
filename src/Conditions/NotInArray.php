<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 */

namespace Frootbox\Db\Conditions;

/**
 *
 */
class NotInArray extends AbstractCondition
{
    /**
     * @return string
     */
    public function toString(): string
    {
        return  $this->column . ' NOT IN ("' . implode('", "', $this->input) . '")';
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return [];
    }
}
