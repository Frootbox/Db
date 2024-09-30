<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 */

namespace Frootbox\Db;

/**
 *
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
     * @param array $record
     * @return mixed
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
     * @param array $result
     * @param \Frootbox\Db\Db $db
     * @param array|null $options
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
     * @return string
     */
    public function __toString(): string
    {
        return get_class($this) . ' (' . $this->getCount() . ')';
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->result = [];
    }

    /**
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
     * @param array $values
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
     * @param string $attribute
     * @return array
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
     * @return array
     */
    public function getData(): array
    {
        return $this->result;
    }

    /**
     * @param int $id
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
     * @param string $glue
     * @param string $attribute
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
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->result);
    }

    /**
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
     * @return void
     */
    public function next(): void
    {
        ++$this->index;
    }

    /**
     * @return mixed
     */
    public function key(): mixed
    {
        return $this->index;
    }

    /**
     * @param string $methodName
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
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->result[$this->index]);
    }

    /**
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
     * @return int
     */
    public function count(): int
    {
        return count($this->result);
    }

    /**
     * @param string $itemId
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
     * @param $columnCount
     * @return array
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
     * @return int
     */
    public function getCount(): int
    {
        return count($this->result);
    }

    /**
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
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
     * @param \Frootbox\Db\Row $row
     * @return void
     */
    public function push(Row $row): void
    {
        $this->result[] = $row;
    }

    /**
     * @param array $record
     * @return void
     */
    public function pushRaw(array $record): void
    {
        $this->result[] = $record;
    }

    /**
     * @param \Frootbox\Db\Result $result
     * @return void
     */
    public function pushResult(\Frootbox\Db\Result $result): void
    {
        foreach ($result as $row) {
            $this->push($row);
        }
    }

    /**
     * @param int $index
     * @return void
     */
    public function removeByIndex(int $index): void
    {
        $total = $this->getTotal();

        $this->total = $total - 1;

        unset($this->result[$index]);
    }

    /**
     * @param string $attribute
     * @param mixed $value
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
     * @return void
     */
    public function reverse(): void
    {
        $this->result = array_reverse($this->result);
    }

    /**
     * @param $className
     * @return $this
     */
    public function setClassName($className)
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @param int $itemsPerPage
     * @return void
     */
    public function setItemsPerPage(int $itemsPerPage): void
    {
        $this->itemsPerPage = $itemsPerPage;
    }

    /**
     * @param int $page
     * @return void
     */
    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    /**
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
     * @param \Frootbox\Db\Row $row
     * @return void
     */
    public function unshift(Row $row)
    {
        array_unshift($this->result, $row);
    }
}
