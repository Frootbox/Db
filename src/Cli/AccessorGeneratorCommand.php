<?php

namespace Frootbox\Db\Cli;

use Frootbox\Db\Db;
use Frootbox\Db\Dbms\Mysql;
use Frootbox\Db\Row;

class AccessorGeneratorCommand
{
    private array $options = [];
    private array $arguments = [];

    public function run(array $argv): int
    {
        $this->parseArguments($argv);

        if (!empty($this->options['help'])) {
            $this->printHelp();

            return 0;
        }

        if (empty($this->arguments[0])) {
            $this->printHelp();

            return 1;
        }

        $path = realpath($this->arguments[0]);

        if ($path === false) {
            fwrite(STDERR, 'Path not found: ' . $this->arguments[0] . PHP_EOL);

            return 1;
        }

        $db = $this->createDb();
        $files = $this->collectPhpFiles($path);
        $changedFiles = 0;

        foreach ($files as $file) {
            $result = $this->processFile($file, $db);

            if ($result === null) {
                continue;
            }

            if ($result['methodCount'] === 0) {
                echo 'No missing accessors: ' . $file . PHP_EOL;
                continue;
            }

            ++$changedFiles;

            if (!empty($this->options['dry-run'])) {
                echo 'Would add ' . $result['methodCount'] . ' accessors to ' . $file . PHP_EOL;
                continue;
            }

            file_put_contents($file, $result['contents']);
            echo 'Added ' . $result['methodCount'] . ' accessors to ' . $file . PHP_EOL;
        }

        echo 'Done. Changed files: ' . $changedFiles . PHP_EOL;

        return 0;
    }

    private function parseArguments(array $argv): void
    {
        foreach ($argv as $argument) {
            if (str_starts_with($argument, '--')) {
                $option = substr($argument, 2);
                $parts = explode('=', $option, 2);
                $this->options[$parts[0]] = $parts[1] ?? true;

                continue;
            }

            $this->arguments[] = $argument;
        }
    }

    private function createDb(): Db
    {
        if (!empty($this->options['bootstrap'])) {
            $bootstrap = realpath($this->options['bootstrap']);

            if ($bootstrap === false) {
                throw new \RuntimeException('Bootstrap file not found: ' . $this->options['bootstrap']);
            }

            $result = require $bootstrap;

            if ($result instanceof Db) {
                return $result;
            }

            if (is_array($result) and ($result['db'] ?? null) instanceof Db) {
                return $result['db'];
            }

            if (isset($db) and $db instanceof Db) {
                return $db;
            }

            throw new \RuntimeException('Bootstrap must return a Frootbox\Db\Db instance or an array with key "db".');
        }

        foreach (['db-host', 'db-schema', 'db-user', 'db-password'] as $option) {
            if (!array_key_exists($option, $this->options)) {
                throw new \RuntimeException('Missing option --' . $option . ' or --bootstrap.');
            }
        }

        return new Db(new Mysql(
            $this->options['db-host'],
            $this->options['db-schema'],
            $this->options['db-user'],
            $this->options['db-password'],
            $this->options['db-charset'] ?? 'utf8mb4'
        ));
    }

    private function collectPhpFiles(string $path): array
    {
        if (is_file($path)) {
            return str_ends_with($path, '.php') ? [$path] : [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

        foreach ($iterator as $file) {
            if (!$file->isFile() or $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    private function processFile(string $file, Db $db): ?array
    {
        $contents = file_get_contents($file);
        $className = $this->getClassName($contents);

        if ($className === null) {
            return null;
        }

        require_once $file;

        if (!class_exists($className) or !is_subclass_of($className, Row::class)) {
            return null;
        }

        $reflection = new \ReflectionClass($className);

        if ($reflection->isAbstract()) {
            return null;
        }

        $table = $this->getTableFromClass($reflection);

        if ($table === null) {
            fwrite(STDERR, 'Skipping ' . $className . ': no $table property found.' . PHP_EOL);

            return null;
        }

        $columns = $this->loadColumns($db, $table);
        $methods = $this->buildMissingMethods($reflection, $columns);

        if (empty($methods)) {
            return [
                'contents' => $contents,
                'methodCount' => 0,
            ];
        }

        return [
            'contents' => $this->insertMethods($contents, $methods),
            'methodCount' => count($methods),
        ];
    }

    private function getClassName(string $contents): ?string
    {
        $tokens = token_get_all($contents);
        $namespace = '';

        for ($index = 0, $count = count($tokens); $index < $count; ++$index) {
            $token = $tokens[$index];

            if (is_array($token) and $token[0] === T_NAMESPACE) {
                $namespace = $this->readName($tokens, $index + 1);
            }

            if (is_array($token) and $token[0] === T_CLASS) {
                $class = $this->readName($tokens, $index + 1);

                return $namespace !== '' ? $namespace . '\\' . $class : $class;
            }
        }

        return null;
    }

    private function readName(array $tokens, int $offset): string
    {
        $name = '';

        for ($index = $offset, $count = count($tokens); $index < $count; ++$index) {
            $token = $tokens[$index];

            if (is_array($token) and in_array($token[0], [T_STRING, T_NAME_QUALIFIED], true)) {
                $name .= $token[1];
                continue;
            }

            if ($token === '\\') {
                $name .= '\\';
                continue;
            }

            if ($name !== '') {
                break;
            }
        }

        return $name;
    }

    private function getTableFromClass(\ReflectionClass $reflection): ?string
    {
        if (!$reflection->hasProperty('table')) {
            return null;
        }

        $row = $reflection->newInstanceWithoutConstructor();
        $property = $reflection->getProperty('table');
        $property->setAccessible(true);
        $table = $property->getValue($row);

        return is_string($table) and $table !== '' ? $table : null;
    }

    private function loadColumns(Db $db, string $table): array
    {
        $stmt = $db->prepare(
            'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table
            ORDER BY ORDINAL_POSITION'
        );

        $stmt->bindValue(':schema', $db->getSchema());
        $stmt->bindValue(':table', $table);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function buildMissingMethods(\ReflectionClass $reflection, array $columns): array
    {
        $methods = [];

        foreach ($columns as $column) {
            $attribute = $column['COLUMN_NAME'];
            $suffix = ucfirst($attribute);
            $type = $this->getPhpType($column);
            $nullableType = $type === 'mixed' ? 'mixed' : '?' . $type;

            $getter = 'get' . $suffix;

            if (!$reflection->hasMethod($getter)) {
                $methods[] = $this->renderGetter($getter, $attribute, $nullableType);
            }

            if (str_starts_with($attribute, 'is') or str_starts_with($attribute, 'has')) {
                if (!$reflection->hasMethod($attribute)) {
                    $methods[] = $this->renderBooleanGetter($attribute, $attribute);
                }
            }

            $setter = 'set' . $suffix;

            if (!$reflection->hasMethod($setter)) {
                $parameter = lcfirst($suffix);
                $methods[] = $this->renderSetter($setter, $attribute, $parameter, $nullableType);
            }
        }

        return $methods;
    }

    private function getPhpType(array $column): string
    {
        $dataType = strtolower($column['DATA_TYPE']);
        $columnType = strtolower($column['COLUMN_TYPE']);

        if ($columnType === 'tinyint(1)' or str_starts_with($column['COLUMN_NAME'], 'is') or str_starts_with($column['COLUMN_NAME'], 'has')) {
            return 'bool';
        }

        return match ($dataType) {
            'int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint' => 'int',
            'float', 'double', 'real' => 'float',
            'json', 'longtext', 'mediumtext', 'text', 'tinytext', 'varchar', 'char', 'date', 'datetime', 'timestamp', 'time' => 'string',
            default => 'mixed',
        };
    }

    private function renderGetter(string $method, string $attribute, string $type): string
    {
        return <<<PHP
    public function {$method}(): {$type}
    {
        return \$this->getAttribute('{$attribute}');
    }
PHP;
    }

    private function renderBooleanGetter(string $method, string $attribute): string
    {
        return <<<PHP
    public function {$method}(): bool
    {
        return !empty(\$this->getAttribute('{$attribute}'));
    }
PHP;
    }

    private function renderSetter(string $method, string $attribute, string $parameter, string $type): string
    {
        return <<<PHP
    public function {$method}({$type} \${$parameter}): static
    {
        return \$this->setAttribute('{$attribute}', \${$parameter});
    }
PHP;
    }

    private function insertMethods(string $contents, array $methods): string
    {
        $position = strrpos($contents, '}');

        if ($position === false) {
            throw new \RuntimeException('Could not find class closing brace.');
        }

        $methodBlock = PHP_EOL . PHP_EOL . implode(PHP_EOL . PHP_EOL, $methods) . PHP_EOL;

        return substr($contents, 0, $position) . $methodBlock . substr($contents, $position);
    }

    private function printHelp(): void
    {
        echo <<<'TXT'
Usage:
  frootbox-db generate-accessors <path> --bootstrap=<file>
  frootbox-db generate-accessors <path> --db-host=<host> --db-schema=<schema> --db-user=<user> --db-password=<pass>

The command scans PHP files for concrete classes extending Frootbox\Db\Row.
Each class needs a protected $table default value so the generator can load
its columns from INFORMATION_SCHEMA.COLUMNS.

TXT;
    }
}
