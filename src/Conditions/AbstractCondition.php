<?php
/**
 * Base class for SQL where-condition objects.
 */

namespace Frootbox\Db\Conditions;


abstract class AbstractCondition implements Interfaces\ConditionInterface
{
    protected $column;
    protected $input;

    protected $uid;

    /**
     * Create a condition for a column and optional input value.
     *
     * @param string $column Column name used in the SQL fragment.
     * @param mixed $input Comparison value or condition-specific input.
     */
    public function __construct(string $column, $input = null)
    {
        $this->column = $column;
        $this->input = $input;
    }

    /**
     * Return a stable placeholder suffix for this condition instance.
     *
     * @return string
     */
    public function getUid(): string
    {
        if (empty($this->uid)) {
            $this->uid = 'param_' . rand(1000, 9999);
        }

        return $this->uid;
    }
}
