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
     * Create a DBMS adapter connection.
     *
     * @param string $host Database host.
     * @param string $schema Database schema name.
     * @param string $user Database user.
     * @param string $password Database password.
     */
    public function __construct(string $host, string $schema, string $user, string $password);

    /**
     * Roll back the active transaction.
     *
     * @return void
     */
    public function rollback(): void;

    /**
     * Start a transaction.
     *
     * @return void
     */
    public function transactionStart(): void;
}
