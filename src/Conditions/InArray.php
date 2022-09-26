<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;


class InArray extends AbstractCondition
{
    /**
     *
     */
    public function toString(): string
    {
        return  $this->column . ' IN ("' . implode('", "', $this->input) . '")';
    }

    /**
     * @return array
     */
    public function getParameters ( ): array {

        return [ ];
    }
}
