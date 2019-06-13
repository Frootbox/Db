<?php
/**
 *
 */

namespace Frootbox\Db\Dbms\Mysql\Queries;


abstract class AbstractQuery implements Interfaces\QueryInterface {

    protected $params;
    protected $extraParameters = [ ];
    protected $parameterIndex = 1;


    /**
     *
     */
    public function __construct ( array $params ) {

        $this->params = $params;
    }


    /**
     *
     */
    abstract protected function getBaseQuery ( ): string;


    /**
     *
     */
    public function getParameters ( ): array {

        $parameters = [ ];

        if (!empty($this->params['data'])) {

            foreach ($this->params['data'] as $key => $value) {

                $parameters[] = new \Frootbox\Db\Conditions\Parameter($key, ':' . $key, $value);
            }
        }

        if (!empty($this->extraParameters)) {

            foreach ($this->extraParameters as $parameter) {
                $parameters[] = $parameter;
            }
        }

        return $parameters;
    }


    /**
     *
     */
    public function appendExtraParameters ( \Frootbox\Db\Conditions\Parameter ...$parameters): Interfaces\QueryInterface {

        foreach ($parameters as $parameter) {

            $this->extraParameters[] = $parameter;
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

                    $this->extraParameters[] = new \Frootbox\Db\Conditions\Parameter($key, $paramKey, $value);
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

        if (empty($this->params['table'])) {
            throw new \Frootbox\Exceptions\ParameterMissing('Missing Parameter "table".');
        }

        return $this->params['table'];
    }


    /**
     *
     */
    public function toString ( ) {

        $sql = $this->getBaseQuery();


        if (!empty($this->params['where'])) {

            $sql .= ' WHERE ';

            if (!isset($this->params['where']['and']) and !isset($this->params['where']['or'])) {

                $where = $this->params['where'];
                unset($this->params['where']);
                $this->params['where']['and'] = $where;
            }

            $sql .= $this->injectQueryConstraint('and', $this->params['where']);
        }


        if (!empty($this->params['order'])) {

            $sql .= ' ORDER BY ';
            $comma = (string) null;

            foreach ($this->params['order'] as $order) {

                $sql .= $comma . $order;
                $comma = ', ';
            }
        }


        if (!empty($this->params['limit'])) {

            $sql .= ' LIMIT ' . $this->params['limit'];
        }


        return $sql;
    }
}