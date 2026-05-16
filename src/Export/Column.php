<?php
/**
 *
 */

namespace Frootbox\Db\Export;

class Column implements \JsonSerializable {

    protected $table;
    protected $data = [ ];

    /**
     * Create a column export wrapper.
     *
     * @param array|null $data Optional INFORMATION_SCHEMA column metadata.
     */
    public function __construct ( array $data = null )
    {

        if ($data !== null) {
            $this->importData($data);
        }
    }


    /**
     * Return the column name.
     *
     * @return string
     */
    public function getName ( )
    {
        return $this->data['COLUMN_NAME'];
    }


    /**
     * Build ALTER TABLE SQL for adding this column after another column.
     *
     * @param string $predecessor Column name that should precede the new column.
     * @return string
     */
    public function getSqlForInsert ( $predecessor ): string
    {

        p($this);

        $sql = 'ALTER TABLE `' . $this->table . '` 
            ADD COLUMN `' . $this->getName() . '` 
            ' . $this->data['COLUMN_TYPE'] . '
            ' . (!empty($this->data['CHARACTER_SET_NAME']) ? 'CHARACTER SET \'' . $this->data['CHARACTER_SET_NAME'] . '\' COLLATE \'' . $this->data['COLLATION_NAME'] . '\'' : '') . '
            ' . ($this->data['IS_NULLABLE'] == 'NO' ? 'NOT NULL' : 'NULL') . '
            ' . (!empty($this->data['COLUMN_DEFAULT']) ? 'DEFAULT \'' . $this->data['COLUMN_DEFAULT'] . '\'' : '') . ' 
            AFTER `' . $predecessor . '`;';

        return $sql;
    }


    /**
     * Build ALTER TABLE SQL for updating this column definition.
     *
     * @param string $predecessor Column name that should precede this column.
     * @return string
     */
    public function getSqlForUpdate ( $predecessor ): string
    {

        $sql = 'ALTER TABLE `' . $this->table . '` 
            CHANGE COLUMN `' . $this->getName() . '` `' . $this->getName() . '` 
            ' . $this->data['COLUMN_TYPE'] . '                         
            ' . (!empty($this->data['CHARACTER_SET_NAME']) ? 'CHARACTER SET \'' . $this->data['CHARACTER_SET_NAME'] . '\' COLLATE \'' . $this->data['COLLATION_NAME'] . '\'' : '') . '
            ' . ($this->data['IS_NULLABLE'] == 'NO' ? 'NOT NULL' : 'NULL') . '
            ' . (strlen($this->data['COLUMN_DEFAULT']) > 0 ? 'DEFAULT \'' . $this->data['COLUMN_DEFAULT'] . '\'' : '') . '
            ' . ((strlen($this->data['COLUMN_DEFAULT']) == 0 AND $this->data['IS_NULLABLE'] == 'YES') ? 'DEFAULT NULL' : '') . '
            
            AFTER `' . $predecessor . '`;';

        return $sql;
    }



    /**
     * Import selected INFORMATION_SCHEMA column metadata fields.
     *
     * @param array<string, mixed> $data Column metadata.
     * @return void
     */
    public function importData ( array $data )
    {

        $importKeys = [
            'COLUMN_NAME',
            'ORDINAL_POSITION',
            'COLUMN_DEFAULT',
            'IS_NULLABLE',
            'DATA_TYPE',
            'CHARACTER_SET_NAME',
            'COLLATION_NAME',
            'COLUMN_TYPE',
            'COLUMN_KEY',
            'EXTRA'
        ];

        foreach ($importKeys as $key) {

            if (!array_key_exists($key, $data)) {
                continue;
            }

            $this->data[$key] = $data[$key];
        }
    }


    /**
     * Compare this column definition with existing column metadata.
     *
     * @param array<string, mixed> $existingColumn Existing column metadata.
     * @return bool
     */
    public function isEqualTo ( array $existingColumn ): bool
    {

        foreach ($this->data as $attribute => $value) {

            if ($existingColumn[$attribute] != $value) {

                return false;
            }
        }

        return true;
    }


    /**
     * Serialize column metadata.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize ( )
    {
        return $this->data;
    }


    /**
     * Set the table name used when generating ALTER TABLE SQL.
     *
     * @param string $table Table name.
     * @return Column
     */
    public function setTable ( $table ): Column
    {
        $this->table = $table;

        return $this;
    }
}
