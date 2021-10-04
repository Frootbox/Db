<?php
/**
 *
 */

namespace Frootbox\Db\Dbms\Mysql\Queries;

class Select extends AbstractQuery
{
    /**
     *
     */
    protected function getBaseQuery(): string
    {

        // TODO Refactor whole code to *NOT* use SELECT * any longer
        if (empty($this->data['select'])) {
            $this->data['select'] = [ '*' ];
        }

        $sql = 'SELECT ';

        if (!empty($this->data['calcFoundRows'])) {
            $sql .= ' SQL_CALC_FOUND_ROWS ';
        }

        $sql .= implode(',', $this->data['select']) . ' FROM ' . $this->getTable();


        return $sql;
    }
}
