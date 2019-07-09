<?php 
/**
 * 
 */

namespace Frootbox\Db;

class Result implements \Iterator {
    
    protected $db;
    protected $className;
    protected $result;    
    protected $index = 0;
    
    
    /**
     * 
     */
    protected function getRow ( array $record ) {
        
        $className = $record['className'] ?? $this->className;
        
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
    public function current ( ) {
     
        return $this->getRow($this->result[$this->index]);
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
    public function key ( ) {
        
        return $this->index;
    }


    /**
     *
     */
    public function map ( string $methodName ): Result {

        foreach ($this as $item) {

            $item->$methodName();
        }

        return $this;
    }
    
    
    /**
     * 
     */
    public function valid ( ) {
        
        return isset($this->result[$this->index]);
    }
    
    
    /**
     * 
     */
    public function rewind ( ) {
        
        $this->index = 0;
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
}