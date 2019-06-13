<?php
/**
 *
 */

namespace Frootbox\Db\Conditions\Interfaces;

interface ConditionInterface {


    /**
     *
     */
    public function toString ( ): string;


    /**
     * @return array
     */
    public function getParameters ( ): array;

}