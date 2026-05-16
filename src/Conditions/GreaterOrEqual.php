<?php


namespace Frootbox\Db\Conditions;


class GreaterOrEqual extends AbstractCondition {

    /**
     * Render a greater-than-or-equal comparison.
     *
     * @return string
     */
    public function toString ( ): string {

        return  $this->column . ' >= :paramCondGOE_1';
    }


    /**
     * Return the bound comparison parameter.
     *
     * @return array<int, Parameter>
     */
    public function getParameters ( ): array {

        return [
            new Parameter($this->column, ':paramCondGOE_1', $this->input)
        ];
    }
}
