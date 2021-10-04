<?php 
/**
 * 
 */

namespace Frootbox\Db;

class Result implements \Iterator, \JsonSerializable
{
    protected $db;
    protected $className;
    protected $result;
    protected $total = null;
    protected $index = 0;
    protected $itemsPerPage = null;
    protected $page = 1;
    
    /**
     * 
     */
    protected function getRow(array $record)
    {
        $className = !empty($record['customClass']) ? $record['customClass'] : (!empty($record['className']) ? $record['className'] : $this->className);

        if (!class_exists($className)) {
            throw new \Exception("Row class missing: " . $className);
        }

        return new $className($record, $this->db);
    }

    /**
     * 
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
     *
     */
    public function __toString(): string
    {
        return get_class($this) . ' (' . $this->getCount() . ')';
    }

    /**
     *
     */
    public function clear(): void
    {
        $this->result = [];
    }
    
    /**
     * 
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
     *
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
     *
     */
    public function getData(): array
    {
        return $this->result;
    }

    /**
     *
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
     *
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
     * 
     */
    public function next(): void
    {
        ++$this->index;
    }
    
    /**
     * 
     */
    public function key(): mixed
    {
        return $this->index;
    }

    /**
     *
     */
    public function map(string $methodName): Result
    {
        foreach ($this as $item) {
            $item->$methodName();
        }

        return $this;
    }
    
    /**
     * 
     */
    public function valid(): bool
    {
        return isset($this->result[$this->index]);
    }
    
    /**
     * 
     */
    public function rewind(): void
    {
        // Reset index
        $this->index = 0;

        // Reset keys
        $this->result = array_values($this->result);
    }
    
    /**
     *
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
     *
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
     * 
     */
    public function getCount ( ) {
        
        return count($this->result);
    }

    /**
     *
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     *
     */
    public function getPages()
    {
        if (empty($this->itemsPerPage)) {
            return 1;
        }

        return ceil($this->getTotal() / $this->itemsPerPage);
    }

    /**
     *
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
     *
     */
    public function push(Row $row): void
    {
        $this->result[] = $row;
    }
    
    /**
     *
     */
    public function pushRaw(array $record): void
    {
        $this->result[] = $record;
    }

    /**
     *
     */
    public function pushResult(\Frootbox\Db\Result $result): void
    {
        foreach ($result as $row) {
            $this->push($row);
        }
    }

    /**
     *
     */
    public function removeByIndex(int $index): void
    {
        $total = $this->getTotal();

        $this->total = $total - 1;

        unset($this->result[$index]);
    }

    /**
     *
     */
    public function reverse ( ) {

        $this->result = array_reverse($this->result);
    }
    
    /**
     * 
     */
    public function setClassName ( $className ) {
        
        $this->className = $className;
        
        return $this;
    }

    /**
     *
     */
    public function setItemsPerPage(int $itemsPerPage): void
    {
        $this->itemsPerPage = $itemsPerPage;
    }

    /**
     *
     */
    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    /**
     *
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
     *
     */
    public function unshift(Row $row)
    {
        array_unshift($this->result, $row);
    }
}
