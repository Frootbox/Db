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

        // TODO Refactor whole code to *NOT* use SELECT * any longer
        if (empty($this->params['select'])) {
            $this->params['select'] = [ '*' ];
        }

        return 'SELECT ' . implode(',', $this->params['select']) . ' FROM ' . $this->getTable();
    }
}
