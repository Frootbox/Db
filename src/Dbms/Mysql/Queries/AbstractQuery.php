<?php
/**
 *
 */

namespace Frootbox\Db\Dbms\Mysql\Queries;


abstract class AbstractQuery implements Interfaces\QueryInterface {

    protected $data;

    protected $parameterIndex = 1;
    protected $parameters = [ ];

    /**
     *
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
     *
     */
    abstract protected function getBaseQuery ( ): string;


    /**
     *
     */
    public function getParameters ( ): array
    {
        return $this->parameters;
    }


    /**
     *
     */
    public function appendExtraParameters ( \Frootbox\Db\Conditions\Parameter ...$parameters): Interfaces\QueryInterface {

        foreach ($parameters as $parameter) {

            $this->parameters[] = $parameter;
        }

        return $this;
    }


    /**
     *
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

                    $paramKey = ':' . $key . '_' . $this->parameterIndex++;
                    $sql .= ' ' . strtoupper($type) . ' ' . $key . ' = ' . $paramKey;

                    $this->parameters[] = new \Frootbox\Db\Conditions\Parameter($key, $paramKey, $value);
                }
            }
        }

        $sql .= ' ) ';


        return trim($sql);
    }


    /**
     *
     */
    public function getTable ( ): string {

        if (empty($this->data['table'])) {
            throw new \Frootbox\Exceptions\ParameterMissing('Missing Parameter "table".');
        }

        return $this->data['table'];
    }


    /**
     *
     */
    public function toString ( ) {

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