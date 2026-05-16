<?php
/**
 * Serializable representation of a database table and its columns.
 */

namespace Frootbox\Db\Export;

class Table implements \JsonSerializable {

    protected $name;
    protected $data = [ ];
    protected $columns = [ ];

    protected $db;


    /**
     * Create an export table wrapper.
     *
     * @param \Frootbox\Db\Db $db Database wrapper.
     * @param string $name Table name.
     * @param array|null $data Optional table metadata.
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
     * Add a column definition to the table export.
     *
     * @param \Frootbox\Db\Export\Column $column Column definition.
     * @return Table
     */
    public function addColumn ( \Frootbox\Db\Export\Column $column ): Table
    {

        $column->setTable($this->getName());

        $this->columns[] = $column;

        return $this;
    }


    /**
     * Check whether the table exists in the current database schema.
     *
     * @return bool
     */
    public function exists ( )
    {

        $sql = 'SHOW TABLES LIKE \'' . $this->name . '\';';
        $stmt = $this->db->query($sql);

        return count($stmt->fetchAll()) > 0;
    }


    /**
     * Return all loaded column definitions.
     *
     * @return array<int, \Frootbox\Db\Export\Column>
     */
    public function getColumns ( )
    {

        return $this->columns;
    }


    /**
     * Return one table metadata value.
     *
     * @param string $attribute Metadata key.
     * @return mixed
     */
    public function getData ( $attribute )
    {
        return $this->data[$attribute] ?? (string) null;
    }


    /**
     * Return the table name.
     *
     * @return string
     */
    public function getName ( )
    {
        return $this->name;
    }


    /**
     * Serialize table and column metadata.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize ( )
    {

        return [
            'table' => $this->data,
            'columns' => $this->columns
        ];
    }


    /**
     * Load columns for this table from INFORMATION_SCHEMA.COLUMNS.
     *
     * @return void
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
