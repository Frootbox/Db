<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;


class MatchColumn extends AbstractCondition {


    /**
     *
     */
    public function toString ( ): string {

        return  $this->column . ' = ' . $this->input;
    }


    /**
     * @return array
     */
    public function getParameters ( ): array {

        return [ ];
    }
}