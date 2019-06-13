<?php


namespace Frootbox\Db\Dbms\Mysql\Queries;


class Update extends AbstractQuery {

    /**
     *
     */
    protected function getBaseQuery ( ): string {

        $sql = 'UPDATE ' . $this->getTable() . ' SET ';

        $loop = 0;
        $comma = '';

        foreach ($this->getParameters() as $parameter) {

            $sql .= $comma . $parameter->getColumn() . ' = ' . $parameter->getKey() . ' ';
            $comma = ', ';
        }

        return $sql;
    }
}