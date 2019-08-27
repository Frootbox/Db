<?php
/**
 *
 */

namespace Frootbox\Db;

abstract class Model {

    protected $table;
    protected $db;

    /**
     *
     */
    public function __construct ( \Frootbox\Db\Db $db ) {

        $this->db = $db;
    }


    /**
     *
     */
    public function fetch ( array $params = null ) {

        $params['table'] = $this->getTable();

        $result = $this->db->fetch($params);
        
        if (!empty($this->class)) {
            $result->setClassName($this->class);
        }

        return $result;
    }


    /**
     *
     */
    public function fetchById ( $rowId ): Row {

        // Generate sql statement
        $stmt = $this->db->query('SELECT * FROM ' . $this->getTable() . ' WHERE id = ' . (int) $rowId . ' LIMIT 1');


        // Fetch all rows
        $record = $stmt->fetch();


        if ($record === false) {
            throw new \Frootbox\Exceptions\NotFound();
        }
        
        $className = $record['className'] ?? $this->class;

        return new $className($record, $this->db);
    }


    /**
     *
     */
    public function fetchByQuery ( $sql ) : \Frootbox\Db\Result {

        // Generate sql statement
        $stmt = $this->db->query($sql);


        // Fetch all rows
        $rows = $stmt->fetchAll();

        $result = new \Frootbox\Db\Result($rows, $this->db, [
            'className' => $this->class
        ]);

        return $result;
    }


    /**
     *
     */
    public function getTable ( ) {

        if (empty($this->table)) {
            throw new \Frootbox\Exceptions\RuntimeError('Missing mandatory Attribute "' . get_class($this) . '::table".');

        }
        return $this->table;
    }


    /**
     *
     */
    public function insert ( \Frootbox\Db\Row $row ): \Frootbox\Db\Row {

        $params = $row->getData();

        unset($params['id'], $params['updated']);

        if (!empty($defaults = $row->getOnInsertDefaults())) {

            foreach ($defaults as $key => $value) {

                if (!isset($params[$key])) {
                    $params[$key] = $value;
                }
            }
        }

        if (isset($params['config']) AND is_array($params['config'])) {
            $params['config'] = json_encode($params['config']);
        }

        if (empty($params['date'])) {
            $params['date'] = date('Y-m-d H:i:s');
        }

        $query = 'INSERT INTO ' . $this->getTable() . ' SET
			updated	=	"' . date('Y-m-d H:i:s') . '"';


        foreach ($params AS $column => $value) {

            if ($value !== null) {
                $query .= "\n," . $column . ' = :' . $column;
            }
            else {
                $query .= "\n," . $column . ' = NULL';
                unset($params[$column]);
            }
        }

        $stmt = $this->db->prepare($query);


        foreach ($params AS $column => $value) {

            if (!empty($value) and $value{0} == '{' and $val = $this->db->getVariable($value)) {
                $value = $val;
            }

            $stmt->bindValue(':' . $column, $value);
        }

        $stmt->execute();


        $rowId = $this->db->getLastInsertId();

        $row->setData([
            'id' => $rowId
        ]);
        
        $row->setDb($this->db);

        return $row;
    }
}