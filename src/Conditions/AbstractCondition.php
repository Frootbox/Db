<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;


abstract class AbstractCondition implements Interfaces\ConditionInterface
{
    protected $column;
    protected $input;


    /**
     *
     */
    public function __construct(string $column, $input = null)
    {
        $this->column = $column;
        $this->input = $input;
    }
