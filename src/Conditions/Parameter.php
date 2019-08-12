<?php


namespace Frootbox\Db\Conditions;


class Parameter {

    protected $column;
    protected $key;
    protected $value;

    protected $skip = false;


    /**
     *
     */
    public function __construct ( $column, $key, $value ) {

        $this->column = $column;
        $this->key = $key;
        $this->value = $value;
    }


    /**
     *
     */
    public function getColumn ( ): string {

        return $this->column;
    }


    /**
     *
     */
    public function getKey ( ): string {

        return $this->key;
    }


    /**
     *
     */
    public function getSkip ( ): bool
    {
        return $this->skip;
    }


    /**
     *
     */
    public function getValue ( ) {

        return $this->value;
    }


    /**
     * Mark parameter to be skipped during query building
     */
    public function setSkip ( bool $shouldSkip ): Parameter {

        $this->skip = $shouldSkip;

        return $this;
    }
}