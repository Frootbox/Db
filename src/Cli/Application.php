<?php

namespace Frootbox\Db\Cli;

class Application
{
    public function run(array $argv): int
    {
        $command = $argv[1] ?? null;

        if ($command === null or in_array($command, ['-h', '--help'], true)) {
            $this->printHelp();

            return 0;
        }

        if ($command !== 'generate-accessors') {
            fwrite(STDERR, 'Unknown command "' . $command . '".' . PHP_EOL . PHP_EOL);
            $this->printHelp();

            return 1;
        }

        require_once __DIR__ . '/AccessorGeneratorCommand.php';

        return (new AccessorGeneratorCommand())->run(array_slice($argv, 2));
    }

    private function printHelp(): void
    {
        echo <<<'TXT'
Frootbox Db CLI

Usage:
  frootbox-db generate-accessors <path> [options]

Options:
  --bootstrap=<file>       PHP file that returns a Frootbox\Db\Db instance or an array with key "db".
  --db-host=<host>         MySQL host.
  --db-schema=<schema>     MySQL schema/database name.
  --db-user=<user>         MySQL user.
  --db-password=<pass>     MySQL password.
  --db-charset=<charset>   MySQL charset, defaults to utf8mb4.
  --dry-run                Show what would change without writing files.
  --help                   Show this help.

TXT;
    }
}
