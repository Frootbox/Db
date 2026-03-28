<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 *
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection SqlNoDataSourceInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

namespace Frootbox\Db\Dbms\Interfaces;

interface Dbms
{
    /**
     * @param string $host
     * @param string $schema
     * @param string $user
     * @param string $password
     */
    public function __construct(string $host, string $schema, string $user, string $password);

    /**
     * @return void
     */
    public function rollback(): void;

    /**
     * @return void
     */
    public function transactionStart(): void;
}
