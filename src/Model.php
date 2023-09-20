<?php
/**
 *
 */

namespace Frootbox\Db;

class Model
{
    protected string $table;
    protected string $class;
    protected \Frootbox\Db\Db $db;

    /**
     * @param \Frootbox\Db\Db $db
     */
    public function __construct(\Frootbox\Db\Db $db)
    {
        $this->db = $db;
    }

    /**
     * @param array|null $params
     * @return \Frootbox\Db\Result
     * @throws \Frootbox\Exceptions\RuntimeError
     */
    public function fetch(array $params = null): Result
    {
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
     * @param array|null $params
     * @param array|null $options
     * @return \Frootbox\Db\Row|null
     */
    public function fetchOne(array $params = null, array $options = null): ?Row
    {
        $params['limit'] = 1;

        $result = $this->fetch($params);

        $row = $result->current();

        if ($row === null and !empty($options['createOnMiss'])) {

            $className = $this->class;
            $row = $this->insert(new $className($params['where']));
        }

        return $row;
    }

    /**
     * @param $rowId
     * @return \Frootbox\Db\Row
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

        return new $className($record, $this->db);
    }

    /**
     * @param $sql
     * @param array|null $params
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
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
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
     * @deprecated
     */
    public function insert(\Frootbox\Db\Row $row): \Frootbox\Db\Row
    {
        return $this->persist($row);
    }

    /**
     * Persist active record
     *
     * @param Row $row
     * @return Row
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

        $stmt->execute();

        $rowId = $this->db->getLastInsertId();

        $row->setData([
            'id' => $rowId,
            'date' => $params['date']
        ]);
        
        $row->setDb($this->db);

        return $row;
    }

    /**
     * @param $class
     * @return void
     */
    public function setClass ( $class ): void
    {
        $this->class = $class;
    }

    /**
     * Set models table
     */
    public function setTable(string $table): void
    {
        $this->table = $table;
    }

    /**
     * Truncate table
     */
    public function truncate(): void
    {
        $sql = 'TRUNCATE `' . $this->getTable() . '`;';

        $this->db->query($sql);
    }
}
