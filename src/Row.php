<?php 
/**
 * 
 */

namespace Frootbox\Db;

class Row implements RowInterface
{
    protected $table;
    protected $model;
    protected $data;
    protected $db = null;
    protected $onInsertDefault = null;
    protected $changed = [ ];

    /**
     *
     */
    public function __construct(array $record = null, Db $db = null)
    {
        $this->data = $record;

        if ($db !== null) {
            $this->db = $db;
        }
    }
    
    /**
     * 
     */
    public function __call($method, array $params = null)
    {
        // Generic getter
        if (substr($method, 0, 3) == 'get') {
            
            $attribute = lcfirst(substr($method, 3));
                        
            if (is_array($this->data) and array_key_exists($attribute, $this->data)) {
                return $this->data[$attribute];                
            }
            
            return null;
        }

        // Generic setter
        if (substr($method, 0, 3) == 'set' and array_key_exists(0, $params)) {

            $attribute = lcfirst(substr($method, 3));

            $this->data[$attribute] = $params[0];
            $this->changed[$attribute] = true;

            return $this;
        }
        
        throw new \Exception('Try to call undefined method "' . $method . '()" on class "' . get_called_class() . '"');
    }

    /**
     *
     */
    public function __toString()
    {
        return 'Row ' . get_class($this) . ' #' . $this->getId();
    }

    /**
     *
     */
    protected function getDb(): Db
    {
        if ($this->db === null) {
            throw new \Frootbox\Exceptions\RuntimeError('Database-Connection object was not set.');
        }

        return $this->db;
    }

    /**
     *
     */
    public function duplicate(): Row
    {
        $row = clone $this;
        $row = $this->getModel()->insert($row);

        return $row;
    }

    /**
     *
     */
    public function getOnInsertDefaults()
    {
        return $this->onInsertDefault;
    }

    /**
     * Delete active record
     */
    public function delete()
    {
        // Obtain database wrapper
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
    public function getData(): array
    {
        return $this->data ?? [];
    }


    /**
     *
     */
    public function getDataRaw ( $attribute )
    {
        return $this->data[$attribute] ?? null;
    }

    /**
     *
     */
    public function getModel(): Model
    {
        return new $this->model($this->db);
    }

    /**
     *
     */
    public function getModelClass(): string
    {
        return $this->model;
    }

    /**
     *
     */
    public function getRepository(): Model
    {
        return new $this->model($this->db);
    }

    /**
     *
     */
    public function getTable ( )
    {
        if (empty($this->table)) {
            throw new \Frootbox\Exceptions\RuntimeError('Missing attribute "table" in class ' . get_class($this));
        }

        return $this->table;
    }

    /**
     *
     */
    public function hasColumn($column): bool
    {
        return array_key_exists($column, $this->data);
    }

    /**
     * Method can be overwritten to perform actions on the object before being inserted into the database
     */
    public function onBeforeInsert(): void
    {

    }

    /**
     *
     */
    public function reload(): void
    {
        $model = $this->getModel();
        $row = $model->fetchById($this->getId());

        $this->data = $row->getData();
    }

    /**
     *
     */
    public function save(): Row
    {
        // Call pre-update function on database record
        if (method_exists($this, 'onBeforeSave')) {
            $this->onBeforeSave();
        }

        $data = $this->getData();

        unset($data['id']);

        $this->setUpdated(date('Y-m-d H:i:s'));

        foreach ($data as $key => $value) {

            if (!array_key_exists($key, $this->changed)) {

                unset($data[$key]);

                continue;
            }

            if (!empty($value) and $value[0] == '{' and $val = $this->getDb()->getVariable($value)) {
                $data[$key] = $val;
            }
        }

        if (!empty($data)) {

            $this->getDb()->update([
                'data' => $data,
                'table' => $this->getTable(),
                'where' => ['id' => $this->getId()],
                'limit' => 1
            ]);
        }

        return $this;
    }

    /**
     *
     */
    public function setData(array $data): \Frootbox\Db\Row
    {
        if (!empty($data['config'])) {

            $this->config = array_replace_recursive($this->config, $data['config']);

            $this->data['config'] = json_encode($this->config);

            $this->changed['config'] = true;

            unset($data['config']);
        }

        foreach ($data as $key => $value) {

            if (array_key_exists($key, $this->data) and $this->data[$key] == $value) {
                continue;
            }

            $this->data[$key] = $value;
            $this->changed[$key] = true;
        }

        return $this;
    }
    
    /**
     * 
     */
    public function setDb(\Frootbox\Db\Db $db)
    {
        $this->db = $db;
        
        return $this;
    }
    
    /**
     * 
     */
    public function setId($id)
    {
        $this->data['id'] = $id;
        
        return $this;
    }

    /**
     *
     */
    public function setModel($model): Row
    {
        $this->model = $model;

        return $this;
    }

    /**
     *
     */
    public function setTable($table): Row
    {
        $this->table = $table;

        return $this;
    }

    /**
     *
     */
    public function unset($mixed): Row
    {
        if (!is_array($mixed)) {
            $mixed = [ $mixed ];
        }

        foreach ($mixed as $attribute) {
            unset($this->data[$attribute]);
        }

        return $this;
    }
}
