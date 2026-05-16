<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;


class LessOrEqual extends AbstractCondition {


    /**
     * Render a less-than-or-equal comparison.
     *
     * @return string
     */
    public function toString ( ): string {

        return  $this->column . ' <= :paramCondLOE_1';
    }


    /**
     * Return the bound comparison parameter.
     *
     * @return array<int, Parameter>
     */
    public function getParameters ( ): array {

        return [
            new Parameter($this->column, ':paramCondLOE_1', $this->input)
        ];
    }
}
