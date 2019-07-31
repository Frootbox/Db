<?php 
/**
 * 
 */

namespace Frootbox\Db;

class Row {

    protected $table;
    protected $model;
    protected $data;
    protected $db = null;
    
    
    /**
     *
     */
    public function __construct ( array $record = null, Db $db = null ) {

        $this->data = $record;

        if ($db !== null) {
            $this->db = $db;
        }
    }
    
    
    /**
     * 
     */
    public function __call ( $method, array $params = null ) {
        

        // Generic getter
        if (substr($method, 0, 3) == 'get') {
            
            $attribute = lcfirst(substr($method, 3));
                        
            if (array_key_exists($attribute, $this->data)) {
                return $this->data[$attribute];                
            }
            
            return null;
        }


        // Generic setter
        if (substr($method, 0, 3) == 'set') {

            $attribute = lcfirst(substr($method, 3));

            $this->data[$attribute] = $params[0];
            
            return $this;
        }


        
        throw new \Exception('Try to call undefined method "' . $method . '()" on class "' . get_called_class() . '"');
    }


    /**
     *
     */
    public function __toString ( ) {

        return 'Row ' . get_class($this) . ' #' . $this->getId();
    }


    /**
     *
     */
    protected function getDb ( ): Db {

        if ($this->db === null) {
            throw new \Frootbox\Exceptions\RuntimeError('Database-Connection object was not set.');
        }

        return $this->db;
    }



    /**
     * Delete active record
     */
    public function delete ( ) {

        $db = $this->getDb();

        $db->delete([
            'table' => $this->getTable(),
            'where' => [ 'id' => $this->getId() ],
            'limit' => 1
        ]);
    }


    /**
     *
     */
    public function getData ( ): array {

        return $this->data;
    }


    /**
     *
     */
    public function getModel ( ): Model {

        return new $this->model($this->db);
    }


    /**
     *
     */
    public function getModelClass ( ): string {

        return $this->model;
    }


    /**
     *
     */
    public function getTable ( ) {

        if (empty($this->table)) {
            throw new \Frootbox\Exceptions\RuntimeError('Missing attribute "table" in class ' . get_class($this));
        }

        return $this->table;
    }


    /**
     *
     */
    public function save ( ): Row {

        if (method_exists($this, 'onBeforeSave')) {
            $this->onBeforeSave();
        }

        $data = $this->getData();

        unset($data['id'], $data['date']);

        $data['updated'] = date('Y-m-d H:i:s');

        foreach ($data as $key => $value) {

            if (!empty($value) and $value{0} == '{' and $val = $this->getDb()->getVariable($value)) {
                $data[$key] = $val;
            }
        }

        $this->getDb()->update([
            'data' => $data,
            'table' => $this->getTable(),
            'where' => [ 'id' => $this->getId() ],
            'limit' => 1
        ]);

        return $this;
    }


    /**
     *
     */
    public function setData ( array $data ): \Frootbox\Db\Row {

        if (!empty($data['config'])) {

            $this->config = array_replace_recursive($this->config, $data['config']);

            $this->data['config'] = json_encode($this->config);

            unset($data['config']);
        }

        foreach ($data as $key => $value) {

            $this->data[$key] = $value;
        }

        return $this;
    }
    
    
    /**
     * 
     */
    public function setDb ( \Frootbox\Db\Db $db ) {
        
        $this->db = $db;
        
        return $this;
    }
    
    
    /**
     * 
     */
    public function setId ( $id ) {
        
        $this->data['id'] = $id;
        
        return $this;
    }


    /**
     *
     */
    public function unset ( $mixed ): Row {

        if (!is_array($mixed)) {
            $mixed = [ $mixed ];
        }

        foreach ($mixed as $attribute) {

            unset($this->data[$attribute]);
        }

        return $this;
    }
}