<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 */

namespace Frootbox\Db;

    /**
     * Lazy iterable wrapper around raw database records.
     */
class Result implements \Iterator, \JsonSerializable, \Countable
{
    protected $db;
    protected $className;
    protected $result;
    protected $total = null;
    protected $index = 0;
    protected $itemsPerPage = null;
    protected $page = 1;

    /**
     * Convert one raw record into its configured row object.
     *
     * @param array<string, mixed> $record Raw database record.
     * @return Row
     * @throws \Exception
     */
    protected function getRow(array $record)
    {
        $className = !empty($record['customClass']) ? $record['customClass'] : (!empty($record['className']) ? $record['className'] : $this->className);

        if (empty($className)) {
            $model = $this->db->getRepository($record['model']);
            $className = $model->getClass();
        }

        if (!class_exists($className)) {
            throw new \Exception("Row class missing: " . $className);
        }

        return new $className($record, $this->db);
    }

    /**
     * Create a result set from raw records.
     *
     * @param array<int, array<string, mixed>|Row> $result Raw records or row objects.
     * @param \Frootbox\Db\Db $db Database wrapper.
     * @param array|null $options Options such as className.
     */
    public function __construct(
        array $result,
        \Frootbox\Db\Db $db,
        array $options = null
    )
    {
        $this->db = $db;
        $this->result = $result;

        if (!empty($options['className'])) {
            $this->className = $options['className'];
        }
    }

    /**
     * Return a readable debug representation of the result.
     *
     * @return string
     */
    public function __toString(): string
    {
        return get_class($this) . ' (' . $this->getCount() . ')';
    }

    /**
     * Remove all rows from the result.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->result = [];
    }

    /**
     * Return the current row, converting raw records lazily.
     *
     * @return \Frootbox\Db\Row|null
     * @throws \Exception
     */
    public function current(): ?Row
    {
        if (!isset($this->result[$this->index])) {
            return null;
        }

        if (!is_object($this->result[$this->index])) {
            $this->result[$this->index] = $this->getRow($this->result[$this->index]);
        }

        return $this->result[$this->index];
    }

    /**
     * Move rows matching one of the given values into a new result.
     *
     * @param array<string, mixed|array<int, mixed>> $values Attribute/value filters.
     * @return $this
     */
    public function extractByValue(array $values): \Frootbox\Db\Result
    {
        $newResult = clone $this;
        $newResult->clear();

        foreach ($this as $index => $row) {

            foreach ($values as $attribute => $value) {

                if (is_array($value)) {

                    foreach ($value as $val) {

                        $getter = 'get' . ucfirst($attribute);

                        if ($row->$getter() == $val) {
                            $newResult->push($row);
                            $this->removeByIndex($index);
                            continue 2;
                        }
                    }
                }
                else {

                }
            }
        }

        return $newResult;
    }

    /**
     * Extract one attribute value from every row.
     *
     * @param string $attribute Attribute name without get-prefix.
     * @return array<int, mixed>
     */
    public function extractValues(string $attribute): array
    {

        $method = 'get' . ucfirst($attribute);

        $list = [];

        foreach ($this as $index => $row) {
            $list[] = $row->$method();
        }

        return $list;
    }

    /**
     * Return the raw internal result array.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->result;
    }

    /**
     * Check whether the result contains a row with the given id.
     *
     * @param int $id Row id.
     * @return bool
     */
    public function hasId(int $id): bool
    {
        foreach ($this->result as $row) {
            if ($row->getId() == $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Join one attribute from all rows into a string.
     *
     * @param string $glue Separator.
     * @param string $attribute Attribute name without get-prefix.
     * @return string
     */
    public function implode(string $glue, string $attribute): string
    {
        $method = 'get' . ucfirst($attribute);

        $list = [ ];

        foreach ($this as $row) {
            $list[] = $row->$method();
        }

        return implode($glue, $list);
    }

    /**
     * Check whether the result contains no rows.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->result);
    }

    /**
     * Serialize all rows for json_encode().
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        $list = [];

        foreach ($this as $row) {

            if ($row instanceof \JsonSerializable) {
                $list[] = $row->jsonSerialize();
            }
            else {
                $list[] = [
                    'id' => $row->getId(),
                ];
            }

        }

        return $list;
    }

    /**
     * Advance the iterator pointer.
     *
     * @return void
     */
    public function next(): void
    {
        ++$this->index;
    }

    /**
     * Return the current iterator key.
     *
     * @return mixed
     */
    public function key(): mixed
    {
        return $this->index;
    }

    /**
     * Call a method on every row in the result.
     *
     * @param string $methodName Method name.
     * @return $this
     */
    public function map(string $methodName): Result
    {
        foreach ($this as $item) {
            $item->$methodName();
        }

        return $this;
    }

    /**
     * Check whether the iterator pointer is valid.
     *
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->result[$this->index]);
    }

    /**
     * Rewind the iterator and normalize internal numeric keys.
     *
     * @return void
     */
    public function rewind(): void
    {
        // Reset index
        $this->index = 0;

        // Reset keys
        $this->result = array_values($this->result);
    }

    /**
     * Count rows in the result.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->result);
    }

    /**
     * Get a row by id from the current result set.
     *
     * @param string $itemId Row id.
     * @return \Frootbox\Db\Row|null
     */
    public function getById(string $itemId): ?Row
    {
        foreach ($this as $item) {
            if ($item->getId() == $itemId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Split rows into a fixed number of columns.
     *
     * @param int $columnCount Number of columns.
     * @return array<int, array<int, Row>>
     */
    public function getColumns($columnCount): array
    {
        $itemsPerColumn = ceil($this->getCount() / $columnCount);

        $columns = [];
        $loop = 0;
        $index = 0;

        foreach ($this as $item) {

            $columns[$index][] = $item;

            if (++$loop % $itemsPerColumn == 0) {
                ++$index;
            }
        }

        return $columns;
    }

    /**
     * Return the number of rows currently loaded in the result.
     *
     * @return int
     */
    public function getCount(): int
    {
        return count($this->result);
    }

    /**
     * Return the current pagination page.
     *
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Return the total number of pagination pages.
     *
     * @return float|int
     */
    public function getPages()
    {
        if (empty($this->itemsPerPage)) {
            return 1;
        }

        return ceil($this->getTotal() / $this->itemsPerPage);
    }

    /**
     * Return the total number of rows for SQL_CALC_FOUND_ROWS queries.
     *
     * @return int
     */
    public function getTotal ( ): int
    {
        if ($this->total !== null) {
            return $this->total;
        }

        $stmt = $this->db->query('SELECT FOUND_ROWS();');

        $this->total = current($stmt->fetch());

        return $this->total;
    }

    /**
     * Append a row object to the result.
     *
     * @param \Frootbox\Db\Row $row Row object.
     * @return void
     */
    public function push(Row $row): void
    {
        $this->result[] = $row;
    }

    /**
     * Append a raw database record to the result.
     *
     * @param array<string, mixed> $record Raw record.
     * @return void
     */
    public function pushRaw(array $record): void
    {
        $this->result[] = $record;
    }

    /**
     * Append every row from another result.
     *
     * @param \Frootbox\Db\Result $result Result to merge.
     * @return void
     */
    public function pushResult(\Frootbox\Db\Result $result): void
    {
        foreach ($result as $row) {
            $this->push($row);
        }
    }

    /**
     * Remove a row by its internal result index.
     *
     * @param int $index Internal index.
     * @return void
     */
    public function removeByIndex(int $index): void
    {
        $total = $this->getTotal();

        $this->total = $total - 1;

        unset($this->result[$index]);
    }

    /**
     * Remove rows whose attribute equals one of the given values.
     *
     * @param string $attribute Attribute name without get-prefix.
     * @param mixed $value Single value or array of values.
     * @return void
     */
    public function removeByValue(string $attribute, mixed $value): void
    {
        if (!is_array($value)) {
            $value = [ $value ];
        }

        foreach ($this as $index => $row) {
            $getter = 'get' . ucfirst($attribute);
            $rowValue = $row->$getter();

            foreach ($value as $checkValue) {
                if ($checkValue == $rowValue) {
                    $this->removeByIndex($index);
                }
            }
        }

        $this->rewind();
    }

    /**
     * Reverse the current result order.
     *
     * @return void
     */
    public function reverse(): void
    {
        $this->result = array_reverse($this->result);
    }

    /**
     * Set the row class used to hydrate raw records.
     *
     * @param class-string<Row> $className Row class name.
     * @return $this
     */
    public function setClassName($className)
    {
        $this->className = $className;

        return $this;
    }

    /**
     * Set the number of items per pagination page.
     *
     * @param int $itemsPerPage Items per page.
     * @return void
     */
    public function setItemsPerPage(int $itemsPerPage): void
    {
        $this->itemsPerPage = $itemsPerPage;
    }

    /**
     * Set the current pagination page.
     *
     * @param int $page Page number.
     * @return void
     */
    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    /**
     * Remove and return the first row from the result.
     *
     * @return \Frootbox\Db\Row|null
     * @throws \Exception
     */
    public function shift(): ?Row
    {
        if (empty($this->result)) {
            return null;
        }

        $shiftedRow = array_shift($this->result);

        return $this->getRow($shiftedRow, $this->db);
    }

    /**
     * Prepend a row object to the result.
     *
     * @param \Frootbox\Db\Row $row Row object.
     * @return void
     */
    public function unshift(Row $row)
    {
        array_unshift($this->result, $row);
    }
}
