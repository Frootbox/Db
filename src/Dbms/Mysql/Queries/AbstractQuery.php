<?php
/**
 * Base class for adapter query builders.
 */

namespace Frootbox\Db\Dbms\Mysql\Queries;


abstract class AbstractQuery implements Interfaces\QueryInterface {

    protected $data;

    protected $parameterIndex = 1;
    protected $parameters = [ ];

    /**
     * Create a query from adapter parameter data.
     *
     * @param array $data Query data such as table, data, where, order, and limit.
     */
    public function __construct ( array $data ) {

        $this->data = $data;

        if (!empty($this->data['data'])) {

            foreach ($this->data['data'] as $key => $value) {

                $this->parameters[] = new \Frootbox\Db\Conditions\Parameter($key, ':' . $key, $value);
            }
        }
    }


    /**
     * Return the SQL fragment before WHERE, ORDER, and LIMIT clauses.
     *
     * @return string
     */
    abstract protected function getBaseQuery ( ): string;


    /**
     * Return all parameters that should be bound to the query.
     *
     * @return array<int, \Frootbox\Db\Conditions\Parameter>
     */
    public function getParameters ( ): array
    {
        return $this->parameters;
    }


    /**
     * Append parameters produced by nested condition objects.
     *
     * @param \Frootbox\Db\Conditions\Parameter ...$parameters Parameters to append.
     * @return Interfaces\QueryInterface
     */
    public function appendExtraParameters ( \Frootbox\Db\Conditions\Parameter ...$parameters): Interfaces\QueryInterface {

        foreach ($parameters as $parameter) {

            $this->parameters[] = $parameter;
        }

        return $this;
    }


    /**
     * Render nested AND/OR where constraints and collect parameters.
     *
     * @param string $type Constraint type, usually "and" or "or".
     * @param array $params Constraint values.
     * @return string
     */
    protected function injectQueryConstraint ( string $type, array $params ) {

        $type = strtolower($type);

        $sql = ($type == 'and' ? ' ( 1 = 1 ' : '( 1 = 0 ');

        foreach ($params as $key => $value) {

            if (strtolower($key) == 'and' OR strtolower($key) == 'or') {
                $sql .= ' AND ' . self::injectQueryConstraint($key, $value);
            }
            else {

                if ($value instanceof \Frootbox\Db\Conditions\Interfaces\ConditionInterface) {

                    $sql .= ' ' . strtoupper($type) . ' ' .$value->toString();

                    $this->appendExtraParameters(...$value->getParameters());
                }
                else {

                    if (strtolower($type) == 'or') {

                        foreach ($params as $column => $values) {

                            foreach ($values as $value) {
                                $paramKey = ':' . $column . '_' . $this->parameterIndex++;
                                $sql .= ' ' . strtoupper($type) . ' ' . $key . ' = ' . $paramKey;

                                $this->parameters[] = new \Frootbox\Db\Conditions\Parameter($key, $paramKey, $value);
                            }

                        }

                    }
                    else {
                        $paramKey = ':' . $key . '_' . $this->parameterIndex++;
                        $sql .= ' ' . strtoupper($type) . ' ' . $key . ' = ' . $paramKey;

                        $this->parameters[] = new \Frootbox\Db\Conditions\Parameter($key, $paramKey, $value);
                    }
                }
            }
        }

        $sql .= ' ) ';


        return trim($sql);
    }


    /**
     * Return the target table from the query data.
     *
     * @return string
     */
    public function getTable ( ): string {

        if (empty($this->data['table'])) {
            throw new \Frootbox\Exceptions\ParameterMissing('Missing Parameter "table".');
        }

        return $this->data['table'];
    }


    /**
     * Render the complete SQL query.
     *
     * @return string
     */
    public function toString()
    {
        $sql = $this->getBaseQuery();

        if (!empty($this->data['where'])) {

            $sql .= ' WHERE ';

            if (!isset($this->data['where']['and']) and !isset($this->data['where']['or'])) {

                $where = $this->data['where'];
                unset($this->data['where']);
                $this->data['where']['and'] = $where;
            }

            $sql .= $this->injectQueryConstraint('and', $this->data['where']);
        }

        if (!empty($this->data['order'])) {

            $sql .= ' ORDER BY ';
            $comma = (string) null;

            foreach ($this->data['order'] as $order) {

                $sql .= $comma . $order;
                $comma = ', ';
            }
        }

        if (!empty($this->data['limit'])) {
            $sql .= ' LIMIT ' . $this->data['limit'];
        }

        return $sql;
    }
}
