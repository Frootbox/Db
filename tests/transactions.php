<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Dbms/Interfaces/Dbms.php';
require dirname(__DIR__) . '/src/Dbms/Mysql.php';
require dirname(__DIR__) . '/src/Db.php';

// Exercise the production PDO transaction methods without a database server.
class TransactionAdapter extends \Frootbox\Db\Dbms\Mysql
{
    public bool $failStart = false;
    public bool $failCommit = false;
    public bool $failRollback = false;

    public function __construct(string $host = '', string $schema = '', string $user = '', string $password = '')
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE entries (value INTEGER NOT NULL)');
    }

    public function transactionStart(): void
    {
        if ($this->failStart) throw new RuntimeException('Start failed');
        parent::transactionStart();
    }

    public function transactionCommit()
    {
        if ($this->failCommit) throw new RuntimeException('Commit failed');
        parent::transactionCommit();
    }

    public function rollback(): void
    {
        if ($this->failRollback) throw new RuntimeException('Rollback failed');
        parent::rollback();
    }

    public function active(): bool { return $this->pdo->inTransaction(); }
}

class TransactionDb extends \Frootbox\Db\Db
{
    public function level(): int { return $this->transactionLevel; }
}

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function failure(callable $action, string $class, string $message): Throwable
{
    try { $action(); } catch (Throwable $error) {
        check($error instanceof $class && $error->getMessage() === $message, 'Unexpected error: ' . $error->getMessage());
        return $error;
    }
    throw new RuntimeException('Expected ' . $class);
}

$tests = [
    'nested commits persist only at the outermost level' => function ($db, $adapter): void {
        $db->transactionStart()->transactionStart();
        $db->query('INSERT INTO entries VALUES (1)');
        $db->transactionCommit();
        check($db->level() === 1 && $adapter->active(), 'Inner commit ended transaction');
        $db->transactionCommit();
        check($db->level() === 0 && !$adapter->active(), 'Outer commit did not finish');
        check((int) $db->query('SELECT COUNT(*) FROM entries')->fetchColumn() === 1, 'Commit lost data');
    },
    'nested rollback preserves the original error and permits a fresh transaction' => function ($db, $adapter): void {
        $original = new RuntimeException('Dispatch failed');
        $caught = failure(function () use ($db, $original): void {
            $db->transactionStart();
            $db->query('INSERT INTO entries VALUES (1)');
            try {
                $db->transactionStart();
                try {
                    $db->query('INSERT INTO entries VALUES (2)');
                    throw $original;
                } catch (Throwable $error) {
                    $db->rollback();
                    throw $error;
                }
            } catch (Throwable $error) {
                $db->rollback();
                throw $error;
            }
        }, RuntimeException::class, 'Dispatch failed');
        check($caught === $original, 'Original exception replaced');
        check($db->level() === 0 && !$adapter->active(), 'Rollback did not reset nesting');
        check((int) $db->query('SELECT COUNT(*) FROM entries')->fetchColumn() === 0, 'Rollback left data');
        failure(fn () => $db->transactionCommit(), LogicException::class, 'No transaction to commit.');
        $db->transactionStart();
        check($adapter->active(), 'New transaction did not start');
        $db->query('INSERT INTO entries VALUES (3)');
        $db->transactionCommit();
        check((int) $db->query('SELECT SUM(value) FROM entries')->fetchColumn() === 3, 'New transaction failed');
    },
    'rollback without a transaction is harmless' => function ($db, $adapter): void {
        $db->rollback();
        $db->rollback();
        check($db->level() === 0 && !$adapter->active(), 'Idle rollback changed state');
    },
    'rollback still supports transactions started directly on the adapter' => function ($db, $adapter): void {
        $adapter->transactionStart();
        $db->query('INSERT INTO entries VALUES (1)');
        $db->rollback();
        check(!$adapter->active(), 'Adapter transaction not rolled back');
        check((int) $db->query('SELECT COUNT(*) FROM entries')->fetchColumn() === 0, 'Adapter rollback left data');
    },
    'unmatched commit cannot corrupt the nesting counter' => function ($db, $adapter): void {
        failure(fn () => $db->transactionCommit(), LogicException::class, 'No transaction to commit.');
        check($db->level() === 0, 'Counter became negative');
        $db->transactionStart()->transactionCommit();
        failure(fn () => $db->transactionCommit(), LogicException::class, 'No transaction to commit.');
        check($db->level() === 0 && !$adapter->active(), 'Duplicate commit changed state');
    },
    'failed commit retains the level until rollback' => function ($db, $adapter): void {
        $db->transactionStart();
        $db->query('INSERT INTO entries VALUES (1)');
        $adapter->failCommit = true;
        failure(fn () => $db->transactionCommit(), RuntimeException::class, 'Commit failed');
        check($db->level() === 1 && $adapter->active(), 'Failed commit lost nesting');
        $db->rollback();
        check($db->level() === 0 && !$adapter->active(), 'Rollback after failed commit failed');
        check((int) $db->query('SELECT COUNT(*) FROM entries')->fetchColumn() === 0, 'Failed commit persisted data');
    },
    'failed start leaves the counter untouched' => function ($db, $adapter): void {
        $adapter->failStart = true;
        failure(fn () => $db->transactionStart(), RuntimeException::class, 'Start failed');
        check($db->level() === 0 && !$adapter->active(), 'Failed start changed nesting');
    },
    'failed rollback propagates the failure and resets nesting' => function ($db, $adapter): void {
        $db->transactionStart()->transactionStart();
        $adapter->failRollback = true;
        failure(fn () => $db->rollback(), RuntimeException::class, 'Rollback failed');
        check($db->level() === 0, 'Failed rollback left stale nesting');
        $adapter->failRollback = false;
        $db->rollback();
        check(!$adapter->active(), 'Cleanup rollback failed');
    },
];

foreach ($tests as $name => $test) {
    $adapter = new TransactionAdapter();
    $test(new TransactionDb($adapter), $adapter);
    echo 'PASS: ', $name, PHP_EOL;
}
echo count($tests), ' transaction tests passed.', PHP_EOL;
