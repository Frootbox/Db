<?php 
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com> 
 * @date 2018-06-18
 */

namespace Frootbox\Db\Dbms;

/**
 * 
 */
class Mysql implements Interfaces\Dbms
{
    protected $pdo;

    protected $schema;
    
    /**
     *  
     */
    public function __construct(\Frootbox\Config\Config $config)
    {
        if ($config !== null) {
            $this->connect($config->database->host, $config->database->schema, $config->database->user, $config->database->password);
        }
    }

    /**
     *
     */
    public function connect($host, $schema, $user, $password): Mysql
    {
        $this->schema = $schema;

        $dsn = 'mysql:dbname=' . $schema . ';host=' . $host . ';charset=utf8mb4';

        $this->pdo = new \PDO($dsn, $user, $password, [
            \PDO::MYSQL_ATTR_FOUND_ROWS => true,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return $this;
    }

    /**
     * Delete record
     */
    public function delete(array $params)
    {
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
    public function execute(Mysql\Queries\Interfaces\QueryInterface $query)
    {
        $sql = $query->toString();

        $stmt = $this->pdo->prepare($sql);

        foreach ($query->getParameters() as $parameter) {

            if ($parameter->getSkip()) {
                continue;
            }

            $stmt->bindValue($parameter->getKey(), $parameter->getValue());
        }

        $stmt->execute();

        return $stmt;
    }

    /**
     *
     */
    public function fetch(array $params): array
    {

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
    public function getSchema ( ): string
    {
        return $this->schema;
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
    public function transactionStart(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
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
    public function update(array $params): void
    {
        $query = new \Frootbox\Db\Dbms\Mysql\Queries\Update($params);

        $this->execute($query);
    }
}