<?php


namespace Frootbox\Db\Dbms\Mysql\Queries;


class Update extends AbstractQuery
{
    /**
     *
     */
    protected function getBaseQuery(): string
    {
        $sql = 'UPDATE ' . $this->getTable() . ' SET ';

        $loop = 0;
        $comma = '';

        foreach ($this->getParameters() as $parameter) {

            if (gettype($parameter->getValue()) != 'NULL') {
                $sql .= $comma . $parameter->getColumn() . ' = ' . $parameter->getKey() . ' ';
            }
            else {
                $sql .= $comma . $parameter->getColumn() . ' = NULL ';

                $parameter->setSkip(true);
            }

            $comma = ', ';
        }

        return $sql;
    }
}
