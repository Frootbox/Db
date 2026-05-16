<?php 
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 */

namespace Frootbox\Db;

class Row implements RowInterface
{
    protected $table;
    protected $model;
    protected $data = [];
    protected $db = null;
    protected $onInsertDefault = null;
    protected $changed = [ ];
    protected string $primaryKey = 'id';
    protected static bool $logDeprecatedMagicCalls = true;
    protected static array $loggedDeprecatedMagicCallSites = [];

    /**
     * @param array|null $record
     * @param Db|null $db
     */
    public function __construct(array $record = null, Db $db = null)
    {
        if ($record !== null) {
            $this->data = $record;
        }

        if ($db !== null) {
            $this->db = $db;
        }
    }
    
    /**
     * @param $method
     * @param array|null $params
     * @return $this|bool|mixed|null
     * @throws \Exception
     */
    public function __call($method, array $params = null)
    {
        // Generic getter
        if (str_starts_with($method, 'get')) {
            $attribute = lcfirst(substr($method, 3));

            $this->logDeprecatedMagicCall($method);

            return $this->getAttribute($attribute);
        }

        // Generic setter
        if (str_starts_with($method, 'set') and array_key_exists(0, $params)) {
            $attribute = lcfirst(substr($method, 3));

            $this->logDeprecatedMagicCall($method);

            return $this->setAttribute($attribute, $params[0]);
        }

        // Generic boolean getter
        if (str_starts_with($method, 'is') and $this->hasAttribute($method)) {
            $this->logDeprecatedMagicCall($method);

            return !empty($this->getAttribute($method));
        }

        // Generic boolean getter
        if (str_starts_with($method, 'has') and $this->hasAttribute($method)) {
            $this->logDeprecatedMagicCall($method);

            return !empty($this->getAttribute($method));
        }
        
        throw new \Exception('Try to call undefined method "' . $method . '()" on class "' . get_called_class() . '"');
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return 'Row ' . get_class($this) . ' #' . $this->getId();
    }

    /**
     * @return Db
     * @throws \Frootbox\Exceptions\RuntimeError
     */
    protected function getDb(): Db
    {
        if ($this->db === null) {
            throw new \Frootbox\Exceptions\RuntimeError('Database-Connection object was not set.');
        }

        return $this->db;
    }

    /**
     * @return Row
     */
    public function duplicate(): Row
    {
        $row = clone $this;
        $row = $this->getModel()->insert($row);

        return $row;
    }

    /**
     * Delete active record
     *
     * @return void
     * @throws \Frootbox\Exceptions\RuntimeError
     */
    public function delete()
    {
        // Obtain database wrapper
        $db = $this->getDb();

        $db->delete([
            'table' => $this->getTable(),
            'where' => [ $this->primaryKey => $this->getPrimaryId() ],
            'limit' => 1
        ]);
    }

    /**
     * Get raw attribute value from the row data.
     *
     * Explicit getters in concrete row classes should use this method instead
     * of relying on __call().
     *
     * @param string $attribute
     * @return mixed|null
     */
    public function getAttribute(string $attribute): mixed
    {
        if (is_array($this->data) and array_key_exists($attribute, $this->data)) {
            return $this->data[$attribute];
        }

        return null;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data ?? [];
    }

    /**
     * @param $attribute
     * @return mixed|null
     */
    public function getDataRaw ( $attribute )
    {
        return $this->getAttribute($attribute);
    }

    /**
     * @return Model
     */
    public function getModel(): Model
    {
        return new $this->model($this->db);
    }

    /**
     * @return string
     */
    public function getModelClass(): string
    {
        return $this->model;
    }

    /**
     * @return mixed|null
     */
    public function getOnInsertDefaults()
    {
        return $this->onInsertDefault;
    }

    /**
     * @return mixed
     */
    public function getPrimaryId(): mixed
    {
        return $this->data[$this->primaryKey] ?? null;
    }

    /**
     * @return Model
     */
    public function getRepository(): Model
    {
        return new $this->model($this->db);
    }

    /**
     * @return mixed
     * @throws \Frootbox\Exceptions\RuntimeError
     */
    public function getTable ( )
    {
        if (empty($this->table)) {
            throw new \Frootbox\Exceptions\RuntimeError('Missing attribute "table" in class ' . get_class($this));
        }

        return $this->table;
    }

    /**
     * Check whether the row currently contains an attribute.
     *
     * @param string $attribute
     * @return bool
     */
    public function hasAttribute(string $attribute): bool
    {
        return is_array($this->data) and array_key_exists($attribute, $this->data);
    }

    /**
     * @param $column
     * @return bool
     */
    public function hasColumn($column): bool
    {
        return $this->hasAttribute($column);
    }

    /**
     * Method can be overwritten to perform actions on the object before being inserted into the database
     */
    public function onBeforeInsert(): void
    {

    }

    /**
     * @return void
     * @throws \Frootbox\Exceptions\NotFound
     */
    public function reload(): void
    {
        $model = $this->getModel();
        $row = $model->fetchById($this->getId());

        $this->data = $row->getData();
    }

    /**
     * @return $this
     * @throws \Frootbox\Exceptions\RuntimeError
     */
    public function save(): Row
    {
        // Call pre-update function on database record
        if (method_exists($this, 'onBeforeSave')) {
            $this->onBeforeSave();
        }

        if (empty($this->changed)) {
            return $this;
        }

        $this->setUpdated(date('Y-m-d H:i:s'));

        $data = $this->getData();

        unset($data['id']);

        foreach ($data as $key => $value) {

            if (!array_key_exists($key, $this->changed)) {

                unset($data[$key]);

                continue;
            }

            if (!empty($value) and is_string($value) and $value[0] == '{' and $val = $this->getDb()->getVariable($value)) {
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
     * @param array $data
     * @return $this
     */
    public function setData(array $data): \Frootbox\Db\Row
    {
        if (!empty($data['config']) && is_array($data['config'])) {

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
     * Set raw attribute value and mark it as changed when needed.
     *
     * Explicit setters in concrete row classes should use this method instead
     * of relying on __call().
     *
     * @param string $attribute
     * @param mixed $value
     * @return $this
     */
    public function setAttribute(string $attribute, mixed $value): static
    {
        if (!is_array($this->data)) {
            $this->data = [];
        }

        if (!$this->hasAttributeChanged($attribute, $value)) {
            return $this;
        }

        // Convert value for boolean setter
        if (str_starts_with($attribute, 'is')) {
            $value = !empty($value) ? 1 : 0;
        }

        // Convert value for boolean setter
        if (str_starts_with($attribute, 'has')) {
            $value = !empty($value) ? 1 : 0;
        }

        $this->data[$attribute] = $value;
        $this->changed[$attribute] = true;

        return $this;
    }

    /**
     * Enable or disable logging for deprecated generic getter/setter calls.
     *
     * @param bool $shouldLog
     * @return void
     */
    public static function setDeprecatedMagicCallLogging(bool $shouldLog): void
    {
        self::$logDeprecatedMagicCalls = $shouldLog;
    }

    /**
     * @param Db $db
     * @return $this
     */
    public function setDb(\Frootbox\Db\Db $db)
    {
        $this->db = $db;
        
        return $this;
    }

    /**
     * @param $id
     * @return $this
     */
    public function setId($id)
    {
        $this->data['id'] = $id;
        
        return $this;
    }

    /**
     * @param $model
     * @return $this
     */
    public function setModel($model): Row
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @param $table
     * @return $this
     */
    public function setTable($table): Row
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @param $mixed
     * @return $this
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

    /**
     * Static constructor
     *
     * Construct row from array input
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): static
    {
        // Instantiate row
        $class = get_called_class();
        $row = new $class;

        // Set data to row
        $row->setData($data);

        return $row;
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    protected function hasAttributeChanged(string $attribute, mixed $value): bool
    {
        if (!$this->hasAttribute($attribute)) {
            return true;
        }

        if ($this->data[$attribute] != $value) {
            return true;
        }

        return is_string($value)
            and is_string($this->data[$attribute])
            and strlen($value) != strlen($this->data[$attribute]);
    }

    /**
     * @param string $method
     * @return void
     */
    protected function logDeprecatedMagicCall(string $method): void
    {
        if (!self::$logDeprecatedMagicCalls) {
            return;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? [];
        $file = $caller['file'] ?? 'unknown file';
        $line = $caller['line'] ?? 'unknown line';
        $key = get_called_class() . '::' . $method . '@' . $file . ':' . $line;

        if (isset(self::$loggedDeprecatedMagicCallSites[$key])) {
            return;
        }

        self::$loggedDeprecatedMagicCallSites[$key] = true;

        error_log(
            'Deprecated Frootbox\\Db\\Row magic call: ' . get_called_class() . '::' . $method . '() '
            . 'was handled by __call(). Add an explicit getter/setter method. '
            . 'Called at ' . $file . ':' . $line . '.'
        );
    }
}
