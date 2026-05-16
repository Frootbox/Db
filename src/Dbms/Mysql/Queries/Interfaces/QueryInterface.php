<?php
/**
 * Class QueryInterface
 */

namespace Frootbox\Db\Dbms\Mysql\Queries\Interfaces;

interface QueryInterface
{

    /**
     * Return the table used by the query.
     *
     * @return string
     */
    public function getTable ( ): string;

}
