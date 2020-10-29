<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;


class NotEqual extends AbstractCondition
{
    /**
     *
     */
    public function toString ( ): string {

        return  $this->column . ' != :' . $this->getUid();
    }


    /**
     * @return array
     */
    public function getParameters ( ): array {

        return [
            new Parameter($this->column, ':' . $this->getUid(), $this->input)
        ];
    }
}