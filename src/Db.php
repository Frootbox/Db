<?php 
/**
 * 
 */

namespace Frootbox\Db;


/**
 * 
 */
class Db {
    
    protected $dbms;
    protected $transactionLevel = 0;


    /**
     * 
     */
    public function __construct ( \Frootbox\Db\Dbms\Interfaces\Dbms $dbms ) {
        
        $this->dbms = $dbms;
    }


    /**
     *
     */
    public function delete ( array $params ) {

        return $this->dbms->delete($params);
    }


    /**
     *
     */
    public function fetch ( array $params ) {

        $rows = $this->dbms->fetch($params);

        $result = new \Frootbox\Db\Result($rows, $this);

        return $result;
    }


    /**
     *
     */
    public function getLastInsertId ( ) {

        return $this->dbms->getLastInsertId();
    }


    /**
     *
     */
    public function getModel ( $model ): Model {

        return new $model($this);
    }


    /**
     *
     */
    public function prepare ( string $sql ) {

        return $this->dbms->prepare($sql);
    }
    
    
    /**
     * 
     */
    public function query ( $sql ) {
        
        return $this->dbms->query($sql);
    }


    /**
     * Begin new transaction
     */
    public function transactionStart ( ): \Frootbox\Db\Db {

        if ($this->transactionLevel == 0) {
            $this->dbms->transactionStart();
        }

        ++$this->transactionLevel;

        return $this;
    }


    /**
     * Commit transaction
     */
    public function transactionCommit ( ): \Frootbox\Db\Db {

        --$this->transactionLevel;

        if ($this->transactionLevel == 0) {
            $this->dbms->transactionCommit();
        }

        return $this;
    }


    /**
     *
     */
    public function update ( array $params ) {

        return $this->dbms->update($params);
    }


    
    /**
     * 
     */
    public static function init ( $wrapper, array $options = null ) {
        
       // d($wrapper);
        
    }
}