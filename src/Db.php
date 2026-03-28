<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 *
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection SqlNoDataSourceInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

namespace Frootbox\Db;

class Db
{
    protected $dbms;
    protected $transactionLevel = 0;
    protected $variables = [ ];

    /**
     * @param Dbms\Interfaces\Dbms $dbms
     */
    public function __construct(\Frootbox\Db\Dbms\Interfaces\Dbms $dbms)
    {
        $this->dbms = $dbms;
    }

    /**
     * @param array $params
     * @return true
     */
    public function delete(array $params)
    {
        return $this->dbms->delete($params);
    }

    /**
     * @param array $params
     * @return Result
     */
    public function fetch(array $params)
    {
        $rows = $this->dbms->fetch($params);

        $result = new \Frootbox\Db\Result($rows, $this);

        return $result;
    }

    /**
     * @return mixed
     */
    public function getLastInsertId()
    {
        return $this->dbms->getLastInsertId();
    }

    /**
     * @deprecated
     *
     * @param $model
     * @return Model
     */
    public function getModel($model): Model
    {
        return new $model($this);
    }

    /**
     * @param $table
     * @return Model
     */
    public function getModelRaw($table): Model
    {
        $model = new Model($this);
        $model->setTable($table);
        $model->setClass(Row::class);

        $model->setTable($table);

        return $model;
    }

    /**
     * @param $model
     * @return Model
     */
    public function getRepository($model): Model
    {
        return new $model($this);
    }

    /**
     * Get connection schema
     *
     * @return string
     */
    public function getSchema(): string
    {
        return $this->dbms->getSchema();
    }

    /**
     * @param $variable
     * @return mixed|null
     */
    public function getVariable($variable)
    {
        return $this->variables[$variable] ?? null;
    }

    /**
     * @param string $sql
     * @return mixed
     */
    public function prepare(string $sql)
    {
        return $this->dbms->prepare($sql);
    }

    /**
     * @param $sql
     * @return mixed
     */
    public function query($sql)
    {
        return $this->dbms->query($sql);
    }

    /**
     * @return void
     */
    public function rollback(): void
    {
        $this->dbms->rollback();
    }

    /**
     * @param $variable
     * @param $value
     * @return $this
     */
    public function setVar($variable, $value): Db
    {
        $this->variables['{' . $variable . '}'] = $value;

        return $this;
    }

    /**
     * Begin new transaction
     *
     * @return $this
     */
    public function transactionStart(): \Frootbox\Db\Db
    {
        if ($this->transactionLevel == 0) {
            $this->dbms->transactionStart();
        }

        ++$this->transactionLevel;

        return $this;
    }

    /**
     * Commit transaction
     *
     * @return $this
     */
    public function transactionCommit(): \Frootbox\Db\Db
    {
        --$this->transactionLevel;

        if ($this->transactionLevel == 0) {
            $this->dbms->transactionCommit();
        }

        return $this;
    }

    /**
     * @param array $params
     * @return null
     */
    public function update(array $params)
    {
        return $this->dbms->update($params);
    }

    /**
     * @param $wrapper
     * @param array|null $options
     * @return void
     */
    public static function init($wrapper, array $options = null)
    {
        
    }
}
