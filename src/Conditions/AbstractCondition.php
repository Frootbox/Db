<?php
/**
 *
 */

namespace Frootbox\Db\Conditions;


abstract class AbstractCondition implements Interfaces\ConditionInterface
{
    protected $column;
    protected $input;

    protected $uid;

    /**
     *
     */
    public function __construct(string $column, $input = null)
    {
        $this->column = $column;
        $this->input = $input;
    }

    /**
     *
     */
    public function getUid(): string
    {
        if (empty($this->uid)) {
            $this->uid = 'param_' . rand(1000, 9999);
        }

        return $this->uid;
    }
}
