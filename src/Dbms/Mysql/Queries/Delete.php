<?php
/**
 *
 */

namespace Frootbox\Db\Dbms\Mysql\Queries;


class Delete extends AbstractQuery {

    /**
     * Render the DELETE base query.
     *
     * @return string
     */
    protected function getBaseQuery ( ): string {

        return 'DELETE FROM ' . $this->getTable();
    }
}
