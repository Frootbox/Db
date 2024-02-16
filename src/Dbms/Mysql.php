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
     * @param string $host
     * @param string $schema
     * @param string $user
     * @param string $password
     * @param string $charset
     */
    public function __construct(string $host, string $schema, string $user, string $password, string $charset = 'utf8mb4')
    {
        $this->connect($host, $schema, $user, $password, $charset);
    }

    /**
     * @param string $host
     * @param string $schema
     * @param string $user
     * @param string $password
     * @return $this
     */
    public function connect(string $host, string $schema, string $user, string $password, string $charset): Mysql
    {
        $this->schema = $schema;

        $dsn = 'mysql:dbname=' . $schema . ';host=' . $host . ';charset=' . $charset;

        $this->pdo = new \PDO($dsn, $user, $password, [
            \PDO::MYSQL_ATTR_FOUND_ROWS => true,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return $this;
    }

    /**
     * @deprecated
     */
    public function delete(array $params)
    {
        $query = new \Frootbox\Db\Dbms\Mysql\Queries\Delete($params);

        $this->execute($query);

        return true;
    }

    /**
     * @param \Frootbox\Db\Dbms\Mysql\Queries\Interfaces\QueryInterface $query
     * @return mixed
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
     * @deprecated
     */
    public function fetch(array $params): array
    {
        $query = new \Frootbox\Db\Dbms\Mysql\Queries\Select($params);

        $result = $this->execute($query);

        return $result->fetchAll();
    }

    /**
     * @return mixed
     */
    public function getLastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * @return string
     */
    public function getSchema(): string
    {
        return $this->schema;
    }

    /**
     * @param string $sql
     * @return mixed
     */
    public function prepare(string $sql)
    {
        $stmt = $this->pdo->prepare($sql);

        return $stmt;
    }

    /**
     * @param $sql
     * @return mixed
     */
    public function query($sql)
    {
        $stmt = $this->pdo->query($sql);

        return $stmt;
    }

    /**
     * @return void
     */
    public function transactionStart(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    /**
     * @return void
     */
    public function transactionCommit()
    {
        $this->pdo->commit();
    }

    /**
     * @deprecated
     *
     * @param array $params
     * @return void
     */
    public function update(array $params): void
    {
        $query = new \Frootbox\Db\Dbms\Mysql\Queries\Update($params);

        $this->execute($query);
    }
}
