<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;

class IsNull extends AbstractCondition
{
    /**
     *
     */
    public function toString(): string
    {
        return  $this->column . ' IS NULL';
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return [
     
        ];
    }
}