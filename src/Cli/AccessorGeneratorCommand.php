<?php

namespace Frootbox\Db\Cli;

use Frootbox\Db\Db;
use Frootbox\Db\Dbms\Mysql;

class AccessorGeneratorCommand
{
    private array $options = [];
    private array $arguments = [];
    private array $classMetadata = [];
    private bool $allowUnknownParentWithTable = false;

    /**
     * Run the accessor generator command.
     *
     * @param array $argv Command arguments without executable and command name.
     * @return int Process exit code.
     */
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

        $this->allowUnknownParentWithTable = is_file($path);

        $db = $this->createDb();
        $files = is_file($path) ? [$path] : $this->collectPhpFiles($path);
        $indexFiles = is_file($path) ? $this->collectPhpFiles(dirname($path)) : $files;
        $this->indexClassMetadata($indexFiles);
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

    /**
     * Split positional arguments and --key=value style options.
     *
     * @param array $argv Command arguments without executable and command name.
     * @return void
     */
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

    /**
     * Create a database wrapper from a bootstrap file, direct MySQL options,
     * or a localconfig.php file in the current project root.
     *
     * @return Db
     */
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

        if ($this->hasDirectDatabaseOptions()) {
            foreach (['db-host', 'db-schema', 'db-user', 'db-password'] as $option) {
                if (!array_key_exists($option, $this->options)) {
                    throw new \RuntimeException('Missing option --' . $option . ' for direct database configuration.');
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

        $localConfig = $this->findLocalConfig();

        if ($localConfig !== null) {
            return $this->createDbFromLocalConfig($localConfig);
        }

        throw new \RuntimeException(
            'Missing database configuration. Provide --bootstrap, direct --db-* options, '
            . 'or run the command from a project root containing localconfig.php.'
        );
    }

    /**
     * Check whether direct database options were provided.
     *
     * @return bool
     */
    private function hasDirectDatabaseOptions(): bool
    {
        foreach (['db-host', 'db-schema', 'db-user', 'db-password', 'db-charset'] as $option) {
            if (array_key_exists($option, $this->options)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Locate the project localconfig.php file.
     *
     * @return string|null Absolute localconfig path, or null when no config exists.
     */
    private function findLocalConfig(): ?string
    {
        if (!empty($this->options['localconfig'])) {
            $path = realpath($this->options['localconfig']);

            if ($path === false) {
                throw new \RuntimeException('Local config file not found: ' . $this->options['localconfig']);
            }

            return $path;
        }

        $path = getcwd() . '/localconfig.php';

        return is_file($path) ? $path : null;
    }

    /**
     * Create a database wrapper from a Frootbox-style localconfig.php array.
     *
     * @param string $path Absolute path to localconfig.php.
     * @return Db
     */
    private function createDbFromLocalConfig(string $path): Db
    {
        $config = require $path;

        if (!is_array($config)) {
            throw new \RuntimeException('Local config must return an array: ' . $path);
        }

        $database = $config['database'] ?? null;

        if (!is_array($database)) {
            throw new \RuntimeException('Local config is missing database configuration: ' . $path);
        }

        $dbms = strtolower($database['dbms'] ?? 'mysql');

        if ($dbms !== 'mysql') {
            throw new \RuntimeException('Unsupported DBMS in local config: ' . $dbms);
        }

        foreach (['host', 'schema', 'user', 'password'] as $key) {
            if (!array_key_exists($key, $database)) {
                throw new \RuntimeException('Local config database section is missing "' . $key . '".');
            }
        }

        return new Db(new Mysql(
            $database['host'],
            $database['schema'],
            $database['user'],
            $database['password'],
            $database['charset'] ?? 'utf8mb4'
        ));
    }

    /**
     * Collect PHP files from a file or directory path.
     *
     * @param string $path Existing file or directory path.
     * @return array<int, string>
     */
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

    /**
     * Generate missing accessor methods for one row class file.
     *
     * @param string $file PHP file to inspect.
     * @param Db $db Database wrapper used to load column metadata.
     * @return array{contents:string, methodCount:int}|null Generated contents and method count, or null when skipped.
     */
    private function processFile(string $file, Db $db): ?array
    {
        $contents = file_get_contents($file);
        $metadata = $this->classMetadata[$file] ?? null;

        if ($metadata === null or $metadata['isAbstract']) {
            return null;
        }

        if (!$this->isRowMetadata($metadata) and !($this->allowUnknownParentWithTable and $metadata['table'] !== null)) {
            return null;
        }

        $table = $metadata['table'];

        if ($table === null) {
            fwrite(STDERR, 'Skipping ' . $metadata['className'] . ': no $table property found.' . PHP_EOL);

            return null;
        }

        $columns = $this->loadColumns($db, $table);
        $methods = $this->buildMissingMethods($this->getAvailableMethods($metadata), $columns);

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

    /**
     * Build source metadata for all declared classes in the scan path.
     *
     * @param array<int, string> $files PHP files collected from the target path.
     * @return void
     */
    private function indexClassMetadata(array $files): void
    {
        foreach ($files as $file) {
            $metadata = $this->getSymbolMetadata(file_get_contents($file));

            if ($metadata === null) {
                continue;
            }

            $metadata['file'] = $file;
            $this->classMetadata[$file] = $metadata;
            $this->classMetadata[$metadata['className']] = $metadata;
        }
    }

    /**
     * Extract class or trait metadata from PHP source without loading the file.
     *
     * @param string $contents PHP source code.
     * @return array<string, mixed>|null Symbol metadata, or null when no supported symbol is found.
     */
    private function getSymbolMetadata(string $contents): ?array
    {
        $tokens = token_get_all($contents);
        $namespace = '';
        $uses = [];

        for ($index = 0, $count = count($tokens); $index < $count; ++$index) {
            $token = $tokens[$index];

            if (is_array($token) and $token[0] === T_NAMESPACE) {
                $namespace = $this->readName($tokens, $index + 1);
            }

            if (is_array($token) and $token[0] === T_USE) {
                $uses = array_replace($uses, $this->readUseStatements($tokens, $index + 1));
            }

            if (is_array($token) and in_array($token[0], [T_CLASS, T_TRAIT], true)) {
                if ($token[0] === T_CLASS and $this->isAnonymousClassToken($tokens, $index)) {
                    continue;
                }

                $class = $this->readName($tokens, $index + 1);
                $className = $namespace !== '' ? $namespace . '\\' . $class : $class;
                $extends = $this->readExtendsName($tokens, $index + 1);
                $classStart = $this->findNextToken($tokens, $index, '{');

                return [
                    'kind' => $token[0] === T_TRAIT ? 'trait' : 'class',
                    'className' => $className,
                    'extends' => $extends !== null ? $this->resolveClassName($extends, $namespace, $uses) : null,
                    'isAbstract' => $this->isAbstractClassToken($tokens, $index),
                    'methods' => $this->readMethodNames($tokens),
                    'traits' => $classStart !== null ? $this->readTraitNames($tokens, $classStart + 1, $namespace, $uses) : [],
                    'table' => $this->readTableProperty($tokens),
                ];
            }
        }

        return null;
    }

    /**
     * Resolve whether metadata belongs to a row class.
     *
     * @param array<string, mixed> $metadata Class metadata.
     * @return bool
     */
    private function isRowMetadata(array $metadata): bool
    {
        if (($metadata['kind'] ?? null) !== 'class') {
            return false;
        }

        $parent = $metadata['extends'] ?? null;

        if ($parent === null) {
            return false;
        }

        $normalizedParent = ltrim($parent, '\\');

        if (in_array($normalizedParent, [
            'Frootbox\Db\Row',
            'Frootbox\Db\Rows\NestedSet',
        ], true)) {
            return true;
        }

        if (!isset($this->classMetadata[$normalizedParent])) {
            return false;
        }

        return $this->isRowMetadata($this->classMetadata[$normalizedParent]);
    }

    /**
     * Return methods declared by the class and known scanned parent classes.
     *
     * @param array<string, mixed> $metadata Class metadata.
     * @return array<string, bool> Method lookup map.
     */
    private function getAvailableMethods(array $metadata): array
    {
        $methods = $metadata['methods'];
        $parent = $metadata['extends'] ?? null;

        foreach ($metadata['traits'] ?? [] as $trait) {
            if (isset($this->classMetadata[$trait])) {
                $methods = array_replace($methods, $this->getAvailableMethods($this->classMetadata[$trait]));
            }
        }

        if ($parent !== null and isset($this->classMetadata[$parent])) {
            $methods = array_replace($this->getAvailableMethods($this->classMetadata[$parent]), $methods);
        }

        return $methods;
    }

    /**
     * Check whether a T_CLASS token belongs to an anonymous class expression.
     *
     * @param array $tokens Tokens returned by token_get_all().
     * @param int $index Current T_CLASS token index.
     * @return bool
     */
    private function isAnonymousClassToken(array $tokens, int $index): bool
    {
        for ($offset = $index - 1; $offset >= 0; --$offset) {
            $token = $tokens[$offset];

            if (is_array($token) and in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) and $token[0] === T_NEW;
        }

        return false;
    }

    /**
     * Check whether a T_CLASS token is preceded by the abstract keyword.
     *
     * @param array $tokens Tokens returned by token_get_all().
     * @param int $index Current T_CLASS token index.
     * @return bool
     */
    private function isAbstractClassToken(array $tokens, int $index): bool
    {
        for ($offset = $index - 1; $offset >= 0; --$offset) {
            $token = $tokens[$offset];

            if (is_array($token) and in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) and $token[0] === T_ABSTRACT;
        }

        return false;
    }

    /**
     * Read a namespace or class name from a token stream.
     *
     * @param array $tokens Tokens returned by token_get_all().
     * @param int $offset Token offset to start reading from.
     * @return string
     */
    private function readName(array $tokens, int $offset): string
    {
        $name = '';

        for ($index = $offset, $count = count($tokens); $index < $count; ++$index) {
            $token = $tokens[$index];

            if (is_array($token) and in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
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

    /**
     * Read use statements before the class declaration.
     *
     * @param array $tokens Tokens returned by token_get_all().
     * @param int $offset Token offset after T_USE.
     * @return array<string, string> Imported class names by alias.
     */
    private function readUseStatements(array $tokens, int $offset): array
    {
        $uses = [];
        $name = '';
        $alias = null;
        $readingAlias = false;

        for ($index = $offset, $count = count($tokens); $index < $count; ++$index) {
            $token = $tokens[$index];

            if ($token === ';' or $token === ',') {
                if ($name !== '') {
                    $parts = explode('\\', $name);
                    $uses[$alias ?? end($parts)] = ltrim($name, '\\');
                }

                if ($token === ';') {
                    break;
                }

                $name = '';
                $alias = null;
                $readingAlias = false;
                continue;
            }

            if (is_array($token) and $token[0] === T_AS) {
                $readingAlias = true;
                continue;
            }

            if (is_array($token) and in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                if ($readingAlias) {
                    $alias = $token[1];
                }
                else {
                    $name .= $token[1];
                }
                continue;
            }

            if ($token === '\\') {
                $name .= '\\';
                continue;
            }

            if ($token === '{') {
                break;
            }
        }

        return $uses;
    }

    /**
     * Find the next literal token after an offset.
     *
     * @param array $tokens Tokens returned by token_get_all().
     * @param int $offset Token offset to start from.
     * @param string $literal Literal token to find.
     * @return int|null Token index, or null when not found.
     */
    private function findNextToken(array $tokens, int $offset, string $literal): ?int
    {
        for ($index = $offset, $count = count($tokens); $index < $count; ++$index) {
            if ($tokens[$index] === $literal) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Read trait names used directly by a class.
     *
     * @param array $tokens Tokens returned by token_get_all().
     * @param int $offset Token offset after the class opening brace.
     * @param string $namespace Current namespace.
     * @param array<string, string> $uses Imported class names by alias.
     * @return array<int, string> Fully qualified trait names.
     */
    private function readTraitNames(array $tokens, int $offset, string $namespace, array $uses): array
    {
        $traits = [];
        $depth = 1;

        for ($index = $offset, $count = count($tokens); $index < $count and $depth > 0; ++$index) {
            $token = $tokens[$index];

            if ($token === '{') {
                ++$depth;
                continue;
            }

            if ($token === '}') {
                --$depth;
                continue;
            }

            if ($depth !== 1 or !is_array($token) or $token[0] !== T_USE) {
                continue;
            }

            for ($cursor = $index + 1; $cursor < $count; ++$cursor) {
                $next = $tokens[$cursor];

                if ($next === ';' or $next === '{') {
                    break;
                }

                if ($next === ',') {
                    continue;
                }

                if (is_array($next) and in_array($next[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
                    $traits[] = $this->resolveClassName($next[1], $namespace, $uses);
                }
            }
        }

        return $traits;
    }

    /**
     * Read the class name from an extends clause.
     *
     * @param array $tokens Tokens returned by token_get_all().
     * @param int $offset Token offset after T_CLASS.
     * @return string|null Raw extends name, or null when none is declared.
     */
    private function readExtendsName(array $tokens, int $offset): ?string
    {
        for ($index = $offset, $count = count($tokens); $index < $count; ++$index) {
            $token = $tokens[$index];

            if ($token === '{') {
                return null;
            }

            if (is_array($token) and $token[0] === T_EXTENDS) {
                return $this->readName($tokens, $index + 1);
            }
        }

        return null;
    }

    /**
     * Resolve a class name using namespace and use imports.
     *
     * @param string $name Raw class name.
     * @param string $namespace Current namespace.
     * @param array<string, string> $uses Imported class names by alias.
     * @return string Fully qualified class name without leading slash.
     */
    private function resolveClassName(string $name, string $namespace, array $uses): string
    {
        $isFullyQualified = str_starts_with($name, '\\');
        $name = ltrim($name, '\\');
        $parts = explode('\\', $name, 2);
        $first = $parts[0];

        if ($isFullyQualified) {
            return $name;
        }

        if (isset($uses[$first])) {
            return $uses[$first] . (isset($parts[1]) ? '\\' . $parts[1] : '');
        }

        if (str_contains($name, '\\')) {
            return $namespace !== '' ? $namespace . '\\' . $name : $name;
        }

        return $namespace !== '' ? $namespace . '\\' . $name : $name;
    }

    /**
     * Read all method names declared in the token stream.
     *
     * @param array $tokens Tokens returned by token_get_all().
     * @return array<string, bool> Method lookup map.
     */
    private function readMethodNames(array $tokens): array
    {
        $methods = array_fill_keys(get_class_methods(\Frootbox\Db\Row::class), true);

        for ($index = 0, $count = count($tokens); $index < $count; ++$index) {
            $token = $tokens[$index];

            if (!is_array($token) or $token[0] !== T_FUNCTION) {
                continue;
            }

            $name = $this->readName($tokens, $index + 1);

            if ($name !== '') {
                $methods[$name] = true;
            }
        }

        return $methods;
    }

    /**
     * Read the default $table property value from a token stream.
     *
     * @param array $tokens Tokens returned by token_get_all().
     * @return string|null Table name, or null when no default table is configured.
     */
    private function readTableProperty(array $tokens): ?string
    {
        for ($index = 0, $count = count($tokens); $index < $count; ++$index) {
            $token = $tokens[$index];

            if (!is_array($token) or $token[0] !== T_VARIABLE or $token[1] !== '$table') {
                continue;
            }

            for ($offset = $index + 1; $offset < $count; ++$offset) {
                $next = $tokens[$offset];

                if ($next === ';') {
                    break;
                }

                if (is_array($next) and $next[0] === T_CONSTANT_ENCAPSED_STRING) {
                    return stripcslashes(substr($next[1], 1, -1));
                }
            }
        }

        return null;
    }

    /**
     * Load column metadata for a table from INFORMATION_SCHEMA.COLUMNS.
     *
     * @param Db $db Database wrapper.
     * @param string $table Table name.
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * Build source code for accessors that are missing from the class.
     *
     * @param array<string, bool> $existingMethods Existing method lookup map.
     * @param array<int, array<string, mixed>> $columns Column metadata rows.
     * @return array<int, string> Method source code blocks.
     */
    private function buildMissingMethods(array $existingMethods, array $columns): array
    {
        $methods = [];

        foreach ($columns as $column) {
            $attribute = $column['COLUMN_NAME'];
            $suffix = ucfirst($attribute);
            $type = $this->getPhpType($column);
            $nullableType = $type === 'mixed' ? 'mixed' : '?' . $type;

            $getter = 'get' . $suffix;

            if (!isset($existingMethods[$getter])) {
                $methods[] = $this->renderGetter($getter, $attribute, $nullableType);
            }

            if (str_starts_with($attribute, 'is') or str_starts_with($attribute, 'has')) {
                if (!isset($existingMethods[$attribute])) {
                    $methods[] = $this->renderBooleanGetter($attribute, $attribute);
                }
            }

            $setter = 'set' . $suffix;

            if (!isset($existingMethods[$setter])) {
                $parameter = lcfirst($suffix);
                $methods[] = $this->renderSetter($setter, $attribute, $parameter, $nullableType);
            }
        }

        return $methods;
    }

    /**
     * Infer a PHP scalar type from MySQL column metadata.
     *
     * @param array<string, mixed> $column Column metadata row.
     * @return string PHP type name.
     */
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

    /**
     * Render a getter method that delegates to Row::getAttribute().
     *
     * @param string $method Method name.
     * @param string $attribute Row attribute name.
     * @param string $type PHP return type.
     * @return string Method source code.
     */
    private function renderGetter(string $method, string $attribute, string $type): string
    {
        return <<<PHP
    public function {$method}(): {$type}
    {
        return \$this->getAttribute('{$attribute}');
    }
PHP;
    }

    /**
     * Render an is-prefix or has-prefix convenience getter for boolean attributes.
     *
     * @param string $method Method name.
     * @param string $attribute Row attribute name.
     * @return string Method source code.
     */
    private function renderBooleanGetter(string $method, string $attribute): string
    {
        return <<<PHP
    public function {$method}(): bool
    {
        return !empty(\$this->getAttribute('{$attribute}'));
    }
PHP;
    }

    /**
     * Render a setter method that delegates to Row::setAttribute().
     *
     * @param string $method Method name.
     * @param string $attribute Row attribute name.
     * @param string $parameter Parameter name.
     * @param string $type PHP parameter type.
     * @return string Method source code.
     */
    private function renderSetter(string $method, string $attribute, string $parameter, string $type): string
    {
        return <<<PHP
    public function {$method}({$type} \${$parameter}): static
    {
        return \$this->setAttribute('{$attribute}', \${$parameter});
    }
PHP;
    }

    /**
     * Insert generated methods before the final class closing brace.
     *
     * @param string $contents Original file contents.
     * @param array<int, string> $methods Method source code blocks.
     * @return string Updated file contents.
     */
    private function insertMethods(string $contents, array $methods): string
    {
        $position = strrpos($contents, '}');

        if ($position === false) {
            throw new \RuntimeException('Could not find class closing brace.');
        }

        $methodBlock = PHP_EOL . PHP_EOL . implode(PHP_EOL . PHP_EOL, $methods) . PHP_EOL;

        return substr($contents, 0, $position) . $methodBlock . substr($contents, $position);
    }

    /**
     * Print command-specific help text.
     *
     * @return void
     */
    private function printHelp(): void
    {
        echo <<<'TXT'
Usage:
  frootbox-db generate-accessors <path> --bootstrap=<file>
  frootbox-db generate-accessors <path>
  frootbox-db generate-accessors <path> --db-host=<host> --db-schema=<schema> --db-user=<user> --db-password=<pass>

The command scans PHP files for concrete classes extending Frootbox\Db\Row.
Each class needs a protected $table default value so the generator can load
its columns from INFORMATION_SCHEMA.COLUMNS.

When no --bootstrap or direct --db-* options are provided, the command reads
./localconfig.php from the current project root. Use --localconfig=<file> to
point to a different config file.

TXT;
    }
}
