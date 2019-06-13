<?php
/**
 *
 */

namespace Frootbox\Db\Dbms\Mysql\Queries;


class Delete extends AbstractQuery {

    /**
     *
     */
    protected function getBaseQuery ( ): string {

        return 'DELETE FROM ' . $this->getTable();
    }
}