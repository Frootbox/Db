<?php 
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com> 
 * @date 2018-06-18
 */

namespace Frootbox\Db\Dbms;

/**
 * 
 */
class Mysql implements Interfaces\Dbms {
    
    protected $pdo;
    
    /**
     *  
     */
    public function __construct ( \Frootbox\Config\Config $config ) {
                
        $dsn = 'mysql:dbname=' . $config->database->schema . ';host=' . $config->database->host . ';charset=utf8';
        
        $this->pdo = new \PDO($dsn, $config->database->user, $config->database->password, [
            \PDO::MYSQL_ATTR_FOUND_ROWS => true,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,

        ]);
    }


    /**
     * Delete record
     */
    public function delete ( array $params ) {

        $query = new \Frootbox\Db\Dbms\Mysql\Queries\Delete($params);

        $this->execute($query);

        /*
        // Delete row
        $stmt = $this->pdo->query('SELECT ROW_COUNT() as deletedRows');
        $stmt->execute();

        $result = $stmt->fetch();

        d($result);
        */

        return true;
    }


    /**
     *
     */
    public function execute ( Mysql\Queries\Interfaces\QueryInterface $query ) {

        $sql = $query->toString();

        $stmt = $this->pdo->prepare($sql);

        foreach ($query->getParameters() as $parameter) {

            $stmt->bindValue($parameter->getKey(), $parameter->getValue());
        }

        $stmt->execute();

        return $stmt;
    }


    /**
     *
     */
    public function fetch ( array $params ) {

        $query = new \Frootbox\Db\Dbms\Mysql\Queries\Select($params);

        $result = $this->execute($query);

        return $result->fetchAll();
    }


    /**
     *
     */
    public function getLastInsertId ( ) {

        return $this->pdo->lastInsertId();
    }


    /**
     *
     */
    public function prepare ( string $sql ) {

        $stmt = $this->pdo->prepare($sql);

        return $stmt;

    }
    
    
    /**
     * 
     */
    public function query ( $sql ) {

        $stmt = $this->pdo->query($sql);

        return $stmt;
    }


    /**
     *
     */
    public function transactionStart ( ) {

        $this->pdo->beginTransaction();
    }


    /**
     *
     */
    public function transactionCommit ( ) {

        $this->pdo->commit();
    }


    /**
     * Update record
     */
    public function update ( array $params ) {

        $query = new \Frootbox\Db\Dbms\Mysql\Queries\Update($params);

        $this->execute($query);


        /*
        // Get affected rows
        $stmt = $this->pdo->query('SELECT ROW_COUNT() as affectedRows');
        $stmt->execute();

        $result = $stmt->fetch();

        d($result);
        */

        return true;
    }
}