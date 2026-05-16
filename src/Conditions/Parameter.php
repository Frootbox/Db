<?php


namespace Frootbox\Db\Conditions;


class Parameter {

    protected $column;
    protected $key;
    protected $value;

    protected $skip = false;


    /**
     * Create a query parameter descriptor.
     *
     * @param string $column Column name.
     * @param string $key Placeholder key, including leading colon.
     * @param mixed $value Bound value.
     */
    public function __construct ( $column, $key, $value ) {

        $this->column = $column;
        $this->key = $key;
        $this->value = $value;
    }


    /**
     * Get the column associated with this parameter.
     *
     * @return string
     */
    public function getColumn ( ): string {

        return $this->column;
    }


    /**
     * Get the placeholder key used in the SQL query.
     *
     * @return string
     */
    public function getKey ( ): string {

        return $this->key;
    }


    /**
     * Check whether this parameter should be skipped during binding.
     *
     * @return bool
     */
    public function getSkip ( ): bool
    {
        return $this->skip;
    }


    /**
     * Get the value that should be bound to the placeholder.
     *
     * @return mixed
     */
    public function getValue ( ) {

        return $this->value;
    }


    /**
     * Mark this parameter to be skipped during binding.
     *
     * @param bool $shouldSkip Whether the parameter should be skipped.
     * @return $this
     */
    public function setSkip ( bool $shouldSkip ): Parameter {

        $this->skip = $shouldSkip;

        return $this;
    }
}
