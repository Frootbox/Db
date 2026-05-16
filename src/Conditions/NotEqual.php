<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;


class NotEqual extends AbstractCondition
{
    /**
     * Render a not-equal comparison.
     *
     * @return string
     */
    public function toString ( ): string {

        return  $this->column . ' != :' . $this->getUid();
    }


    /**
     * Return the bound comparison parameter.
     *
     * @return array<int, Parameter>
     */
    public function getParameters ( ): array {

        return [
            new Parameter($this->column, ':' . $this->getUid(), $this->input)
        ];
    }
}
