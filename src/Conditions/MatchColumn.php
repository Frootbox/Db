<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;


class MatchColumn extends AbstractCondition {


    /**
     * Render a comparison between two columns.
     *
     * @return string
     */
    public function toString ( ): string {

        return  $this->column . ' = ' . $this->input;
    }


    /**
     * Return bound parameters for the condition.
     *
     * @return array<int, Parameter>
     */
    public function getParameters ( ): array {

        return [ ];
    }
}
