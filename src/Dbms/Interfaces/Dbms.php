<?php 
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com> 
 * @date 2018-06-21
 */

namespace Frootbox\Db\Dbms\Interfaces;

/**
 * 
 */
interface Dbms
{
    /**
     *
     */
    public function __construct(string $host, string $schema, string $user, string $password);

    /**
     *
     */
    public function transactionStart(): void;
}
