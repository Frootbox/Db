<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;

class IsNotNull extends AbstractCondition
{
    /**
     *
     */
    public function toString(): string
    {
        return  $this->column . ' IS NOT NULL';
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