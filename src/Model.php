<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 */

namespace Frootbox\Db;

/**
 * @template TRow of Row
 */
class Model
{
    protected string $table;

    /**
     * @var class-string<TRow>
     */
    protected string $class;

    /**
     * Create a model bound to a database connection.
     *
     * @param \Frootbox\Db\Db $db Database wrapper.
     */
    public function __construct(
        protected \Frootbox\Db\Db $db,
    )
    { }

    /**
     * Convert PDO integrity exceptions into package-specific exceptions.
     *
     * @param \PDOException $e Original PDO exception.
     * @return \Throwable Mapped exception.
     */
    private function mapException(\PDOException $e): \Throwable
    {
        $sqlState = $e->getCode();
        $message = $e->getMessage();

        // Integrity constraint violation
        if ($sqlState === '23000') {

            // UNIQUE constraint
            if (str_contains($message, 'Duplicate entry')) {
                return new \Frootbox\Db\Exception\UniqueConstraintViolationException(
                    message: 'Unique constraint violated',
                    previous: $e
                );
            }

            // Foreign key constraint
            if (str_contains($message, 'foreign key constraint fails')) {
                return new \Frootbox\Db\Exception\ForeignKeyViolationException(
                    message: 'Foreign key constraint violated',
                    previous: $e
                );
            }

            return new \Frootbox\Db\Exception\ConstraintViolationException(
                message: 'Integrity constraint violated',
                previous: $e
            );
        }

        // Fallback → unverändert durchreichen
        return $e;
    }

    /**
     * Fetch multiple rows from the model table.
     *
     * @param array|null $params Select parameters.
     * @param array|null $where Optional where constraints merged into $params.
     * @param array|null $order Optional order clauses merged into $params.
     * @param int|null $limit Optional limit merged into $params.
     * @param bool $calcFoundRows Whether to request the total row count.
     * @return Result
     * @throws \Frootbox\Exceptions\RuntimeError
     */
    public function fetch(
        array $params = null,
        array $where = null,
        array $order = null,
        int $limit = null,
        bool $calcFoundRows = false,
    ): Result
    {
        if (!empty($where)) {
            $params['where'] = $where;
        }

        if (!empty($order)) {
            $params['order'] = $order;
        }

        if (!empty($limit)) {
            $params['limit'] = $limit;
        }

        if ($calcFoundRows) {
            $params['calcFoundRows'] = true;
        }

        $params['table'] = $this->getTable();
        $itemsPerPage = $params['limit'] ?? null;

        if (!empty($params['page']) and $params['page'] > 1) {

            $offset = $params['limit'] * $params['page'] - $params['limit'];

            $params['limit'] = $offset . ',' . $params['limit'];
        }

        $result = $this->db->fetch($params);
        
        if (!empty($params['limit'])) {
            $result->setItemsPerPage($itemsPerPage);
        }

        if (!empty($params['page'])) {
            $result->setPage($params['page']);
        }

        if (!empty($this->class)) {
            $result->setClassName($this->class);
        }

        if (!empty($params['calcFoundRows'])) {
            $result->getTotal();
        }

        return $result;
    }

    /**
     * Fetch the first row matching the given parameters.
     *
     * @param array|null $params Select parameters.
     * @param array|null $options Options such as createOnMiss.
     * @param array|null $where Optional where constraints merged into $params.
     * @param array|null $order Optional order clauses merged into $params.
     * @return TRow|null
     * @throws \Frootbox\Exceptions\RuntimeError
     */
    public function fetchOne(
        array $params = null,
        array $options = null,
        array $where = null,
        array $order = null,
    ): ?Row
    {
        $params['limit'] = 1;

        if (!empty($where)) {
            $params['where'] = $where;
        }

        if (!empty($order)) {
            $params['order'] = $order;
        }

        $result = $this->fetch($params);

        /** @var TRow|null $row */
        $row = $result->current();

        if ($row === null and !empty($options['createOnMiss'])) {

            $className = $this->class;
            $row = $this->insert(new $className($params['where']));
        }

        return $row;
    }

    /**
     * Fetch one row by its numeric id.
     *
     * @param int|string $rowId Row id.
     * @return TRow
     * @throws \Frootbox\Exceptions\NotFound
     * @throws \Frootbox\Exceptions\RuntimeError
     */
    public function fetchById($rowId): Row
    {
        // Generate sql statement
        $stmt = $this->db->query('SELECT * FROM ' . $this->getTable() . ' WHERE id = ' . (int) $rowId . ' LIMIT 1');

        // Fetch all rows
        $record = $stmt->fetch();

        if ($record === false) {
            throw new \Frootbox\Exceptions\NotFound('Database record #' . $rowId . ' not found.');
        }

        $className = $record['customClass'] ?? $record['className'] ?? $this->getClass();

        /** @var class-string<TRow> $className */
        return new $className($record, $this->db);
    }

    /**
     * Fetch rows using a raw prepared SQL query.
     *
     * @param string $sql SQL query.
     * @param array|null $params Placeholder values keyed by placeholder name.
     * @return \Frootbox\Db\Result
     */
    public function fetchByQuery($sql, array $params = null): \Frootbox\Db\Result
    {
        // Prepare sql statement
        $stmt = $this->db->prepare($sql);

        if (!empty($params)) {

            foreach ($params as $tag => $value) {
                $stmt->bindValue($tag, $value);
            }
        }

        $stmt->execute();

        // Fetch all rows
        $rows = $stmt->fetchAll();

        // Generate result
        $result = new \Frootbox\Db\Result($rows, $this->db, [
            'className' => $this->getClass(),
        ]);

        return $result;
    }

    /**
     * Get the row class used by this model.
     *
     * @return class-string<TRow>
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Get the table name managed by this model.
     *
     * @return string
     * @throws \Frootbox\Exceptions\RuntimeError
     */
    public function getTable(): string
    {
        if (empty($this->table)) {
            throw new \Frootbox\Exceptions\RuntimeError('Missing mandatory Attribute "' . get_class($this) . '::table".');

        }
        return $this->table;
    }

    /**
     * Persist a new row.
     *
     * @deprecated Use persist() instead.
     *
     * @param \Frootbox\Db\Row $row Row to insert.
     * @return \Frootbox\Db\Row Persisted row with id and database connection.
     */
    public function insert(\Frootbox\Db\Row $row): \Frootbox\Db\Row
    {
        return $this->persist($row);
    }

    /**
     * Persist a new active record to the model table.
     *
     * @param Row $row Row to insert.
     * @return Row Persisted row with id and database connection.
     */
    public function persist(\Frootbox\Db\Row $row): \Frootbox\Db\Row
    {
        // Perform onBeforeInsert method on row
        $row->onBeforeInsert();

        // Obtain row data
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

            if (!empty($value) and is_string($value) and $value[0] == '{' and $val = $this->db->getVariable($value)) {
                $value = $val;
            }

            $stmt->bindValue(':' . $column, $value);
        }

        try {

            // Execute query
            $stmt->execute();
        }
        catch (\PDOException $e) {
            throw $this->mapException($e);
        }

        $rowId = $this->db->getLastInsertId();

        $row->setData([
            'id' => $rowId,
            'date' => $params['date']
        ]);
        
        $row->setDb($this->db);

        return $row;
    }

    /**
     * Set the row class used by this model.
     *
     * @param class-string<TRow> $class Row class name.
     * @return void
     */
    public function setClass ( $class ): void
    {
        $this->class = $class;
    }

    /**
     * Set the table managed by this model.
     *
     * @param string $table Table name.
     * @return void
     */
    public function setTable(string $table): void
    {
        $this->table = $table;
    }

    /**
     * Truncate the model table.
     *
     * @return void
     */
    public function truncate(): void
    {
        $sql = 'TRUNCATE `' . $this->getTable() . '`;';

        $this->db->query($sql);
    }
}
