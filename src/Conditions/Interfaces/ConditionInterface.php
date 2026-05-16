<?php
/**
 * Contract for query conditions that render SQL and bound parameters.
 */

namespace Frootbox\Db\Conditions\Interfaces;

interface ConditionInterface {


    /**
     * Render the SQL condition fragment.
     *
     * @return string
     */
    public function toString ( ): string;


    /**
     * Return parameters that must be bound for the SQL fragment.
     *
     * @return array<int, \Frootbox\Db\Conditions\Parameter>
     */
    public function getParameters ( ): array;

}
