<?php
/**
 *
 */

namespace Frootbox\Db\Dbms\Mysql\Queries;

class Select extends AbstractQuery {

    /**
     *
     */
    protected function getBaseQuery ( ): string {

        return 'SELECT * FROM ' . $this->getTable();
    }
}
