<?php

namespace Frootbox\Db\Cli;

class Application
{
    /**
     * Dispatch the command line invocation to the requested CLI command.
     *
     * @param array $argv Raw command line arguments including the executable name.
     * @return int Process exit code.
     */
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

        try {
            require_once __DIR__ . '/AccessorGeneratorCommand.php';

            return (new AccessorGeneratorCommand())->run(array_slice($argv, 2));
        }
        catch (\Throwable $e) {
            fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);

            return 1;
        }
    }

    /**
     * Print the top-level CLI help text.
     *
     * @return void
     */
    private function printHelp(): void
    {
        echo <<<'TXT'
Frootbox Db CLI

Usage:
  frootbox-db generate-accessors <path> [options]

Options:
  --bootstrap=<file>       PHP file that returns a Frootbox\Db\Db instance or an array with key "db".
  --localconfig=<file>     Project config array, defaults to ./localconfig.php when present.
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
