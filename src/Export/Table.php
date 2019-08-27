<?php
/**
 *
 */

namespace Frootbox\Db\Export;

class Table implements \JsonSerializable {

    protected $name;
    protected $data = [ ];
    protected $columns = [ ];

    protected $db;


    /**
     *
     */
    public function __construct( \Frootbox\Db\Db $db, string $name, array $data = null )
    {
        $this->name = $name;

        $this->data = [
            'Name' => $name,
            'Engine' => $data['Engine'] ?? null,
            'Collation' => $data['Collation'] ?? null
        ];

        $this->db = $db;
    }


    /**
     *
     */
    public function addColumn ( \Frootbox\Db\Export\Column $column ): Table
    {

        $column->setTable($this->getName());

        $this->columns[] = $column;

        return $this;
    }


    /**
     *
     */
    public function exists ( )
    {

        $sql = 'SHOW TABLES LIKE \'' . $this->name . '\';';
        $stmt = $this->db->query($sql);

        return count($stmt->fetchAll()) > 0;
    }


    /**
     *
     */
    public function getColumns ( )
    {

        return $this->columns;
    }


    /**
     *
     */
    public function getData ( $attribute )
    {
        return $this->data[$attribute] ?? (string) null;
    }


    /**
     *
     */
    public function getName ( )
    {
        return $this->name;
    }


    /**
     *
     */
    public function jsonSerialize ( )
    {

        return [
            'table' => $this->data,
            'columns' => $this->columns
        ];
    }


    /**
     *
     */
    public function loadColumns ( )
    {

        $sql = 'SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = \'' . $this->db->getSchema() . '\' AND TABLE_NAME = \'' . $this->name . '\' ORDER BY ORDINAL_POSITION';
        $columns = $this->db->query($sql)->fetchAll();

        foreach ($columns as $column) {

            $this->columns[] = new Column($column);
        }
    }
}