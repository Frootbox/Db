<?php
/**
 *
 */

namespace Frootbox\Db\Export;

class Factory {

    protected $db;

    /**
     *
     */
    public function __construct ( \Frootbox\Db\Db $db )
    {
        $this->db = $db;
    }


    /**
     *
     */
    public function export ( $type = 'array' )
    {

        $tables = $this->getTables();

        foreach ($tables as $table) {

            $table->loadColumns();
        }

        $json = json_encode($tables);

        switch ( $type ) {

            case 'php':
                return '<?php return ' . var_export(json_decode($json, true), true) . ';';
        }

        $data = json_decode();
        return ;
    }


    /**
     *
     */
    public function getTables ( )
    {

        // Fetch table list
        $stmt = $this->db->query('SHOW TABLE STATUS');
        $result = $stmt->fetchAll();

        $list = [ ];

        foreach ($result as $table) {

            $list[] = new Table($this->db, $table['Name'], $table);
        }

        return $list;
    }
}