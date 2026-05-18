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
     * Create a row from an optional database record and connection.
     *
     * @param array|null $record Raw column values.
     * @param Db|null $db Database wrapper used for persistence operations.
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
     * Handle legacy dynamic accessor methods with get, set, is, or has prefixes.
     *
     * Explicit methods should be preferred in new row classes. This method is
     * kept for backward compatibility and logs deprecated dynamic access.
     *
     * @param string $method Called method name.
     * @param array|null $params Called method parameters.
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
     * Return a readable debug representation of the row.
     *
     * @return string
     */
    public function __toString()
    {
        return 'Row ' . get_class($this) . ' #' . $this->getId();
    }

    /**
     * Get the database connection assigned to this row.
     *
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
     * Clone and insert the current row as a new database record.
     *
     * @return Row Newly persisted duplicate row.
     */
    public function duplicate(): Row
    {
        $row = clone $this;
        $row = $this->getModel()->insert($row);

        return $row;
    }

    /**
     * Delete this row from its table.
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
     * Get a raw attribute value from the row data.
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
     * Return all raw row data.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data ?? [];
    }

    /**
     * Get the creation timestamp stored in the conventional date column.
     *
     * @return string|null
     */
    public function getDate(): ?string
    {
        return $this->getAttribute('date');
    }

    /**
     * Get a raw attribute value.
     *
     * @param string $attribute Attribute name.
     * @return mixed|null
     */
    public function getDataRaw ( $attribute )
    {
        return $this->getAttribute($attribute);
    }

    /**
     * Get the conventional numeric row id.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        $id = $this->getAttribute('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Create this row's model/repository instance.
     *
     * @return Model
     */
    public function getModel(): Model
    {
        return new $this->model($this->db);
    }

    /**
     * Get the configured model class name.
     *
     * @return string
     */
    public function getModelClass(): string
    {
        return $this->model;
    }

    /**
     * Get default values applied when the row is inserted.
     *
     * @return array|null
     */
    public function getOnInsertDefaults()
    {
        return $this->onInsertDefault;
    }

    /**
     * Get the value of the configured primary key.
     *
     * @return mixed
     */
    public function getPrimaryId(): mixed
    {
        return $this->data[$this->primaryKey] ?? null;
    }

    /**
     * Create this row's repository instance.
     *
     * @return Model
     */
    public function getRepository(): Model
    {
        return new $this->model($this->db);
    }

    /**
     * Get the table name assigned to this row.
     *
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
     * Get the update timestamp stored in the conventional updated column.
     *
     * @return string|null
     */
    public function getUpdated(): ?string
    {
        return $this->getAttribute('updated');
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
     * Check whether a raw column value exists on this row.
     *
     * @param string $column Column name.
     * @return bool
     */
    public function hasColumn($column): bool
    {
        return $this->hasAttribute($column);
    }

    /**
     * Hook called before the row is inserted.
     *
     * Override this in subclasses to prepare row data before persistence.
     *
     * @return void
     */
    public function onBeforeInsert(): void
    {

    }

    /**
     * Reload this row's data from the database.
     *
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
     * Persist changed attributes on this row.
     *
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
     * Merge raw data into the row and mark changed values.
     *
     * @param array<string, mixed> $data Raw row data.
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
     * Set a raw attribute value and mark it as changed when needed.
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

        if (is_bool($value)) {
            $value = !empty($value) ? 1 : 0;
        }

        // Convert value for boolean setter
        if (str_starts_with($attribute, 'is')) {
            $value = !empty($value) ? 1 : 0;
        }

        // Convert value for boolean setter
        if (str_starts_with($attribute, 'has')) {
            $value = !empty($value) ? 1 : 0;
        }

        if (!$this->hasAttributeChanged($attribute, $value)) {
            return $this;
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
     * Assign a database connection to this row.
     *
     * @param Db $db Database wrapper.
     * @return $this
     */
    public function setDb(\Frootbox\Db\Db $db)
    {
        $this->db = $db;
        
        return $this;
    }

    /**
     * Set the creation timestamp stored in the conventional date column.
     *
     * @param string|null $date Creation timestamp.
     * @return $this
     */
    public function setDate(?string $date): static
    {
        return $this->setAttribute('date', $date);
    }

    /**
     * Set the row id.
     *
     * @param mixed $id Row id.
     * @return $this
     */
    public function setId($id): static
    {
        return $this->setAttribute('id', $id);
    }

    /**
     * Set the model/repository class name.
     *
     * @param class-string<Model> $model Model class name.
     * @return $this
     */
    public function setModel($model): Row
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set the database table name.
     *
     * @param string $table Table name.
     * @return $this
     */
    public function setTable($table): Row
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Set the update timestamp stored in the conventional updated column.
     *
     * @param string|null $updated Update timestamp.
     * @return $this
     */
    public function setUpdated(?string $updated): static
    {
        return $this->setAttribute('updated', $updated);
    }

    /**
     * Remove one or more raw attributes from the row data.
     *
     * @param string|array<int, string> $mixed Attribute name or list of names.
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
     * Construct a row from an array without assigning a database connection.
     *
     * @param array<string, mixed> $data Raw row data.
     * @return static
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
     * Check whether assigning a value would change an attribute.
     *
     * @param string $attribute Attribute name.
     * @param mixed $value New value.
     * @return bool
     */
    protected function hasAttributeChanged(string $attribute, mixed $value): bool
    {
        if (!$this->hasAttribute($attribute)) {
            return true;
        }

        if ($this->data[$attribute] !== $value) {
            return true;
        }

        return false;
    }

    /**
     * Log one deprecation message for a legacy magic call site.
     *
     * @param string $method Method name handled by __call().
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
