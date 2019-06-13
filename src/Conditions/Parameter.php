<?php


namespace Frootbox\Db\Conditions;


class Parameter {

    protected $column;
    protected $key;
    protected $value;


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
    public function getValue ( ): string {

        return (string) $this->value;
    }
}