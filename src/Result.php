<?php 
/**
 * 
 */

namespace Frootbox\Db;

class Result implements \Iterator
{
    protected $db;
    protected $className;
    protected $result;
    protected $total = null;
    protected $index = 0;
    
    /**
     * 
     */
    protected function getRow ( array $record ) {
        
        $className = $record['className'] ?? $this->className;
        
        if (!class_exists($className)) {
            throw new \Exception("nöööö " . $className);
        }

        return new $className($record, $this->db);
    }

    /**
     * 
     */
    public function __construct ( array $result, \Frootbox\Db\Db $db, array $options = null ) {
        
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
    public function next ( ) {
        
        ++$this->index;
    }
    
    /**
     * 
     */
    public function key()
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
    public function valid()
    {
        return isset($this->result[$this->index]);
    }
    
    /**
     * 
     */
    public function rewind()
    {
        // Reset index
        $this->index = 0;

        // Reset keys
        $this->result = array_values($this->result);
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
    public function push(Row $row)
    {
        $this->result[] = $row;
    }

    /**
     *
     */
    public function removeByIndex(int $index): void
    {
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
    public function shift ( ) {

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
