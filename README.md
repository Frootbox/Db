# Frootbox Db

Frootbox Db is a small PHP database abstraction and Active Record layer for MySQL.
It wraps `PDO`, provides a lightweight query builder, maps result rows to row
objects, and offers a migration path from dynamic Active Record accessors to
explicit getter and setter methods.

The package is intentionally compact. It is useful when you want simple models
and rows without adopting a full ORM.

## Installation

Install the package with Composer:

```bash
composer require frootbox/db
```

Requirements:

- PHP 8.0 or newer
- `ext-pdo`
- MySQL or MariaDB when using the bundled MySQL adapter

## Creating A Connection

Create a DBMS adapter and wrap it in `Frootbox\Db\Db`:

```php
use Frootbox\Db\Db;
use Frootbox\Db\Dbms\Mysql;

$db = new Db(new Mysql(
    host: 'localhost',
    schema: 'app',
    user: 'app',
    password: 'secret',
));
```

`Db` exposes low-level methods such as `query()`, `prepare()`, `fetch()`,
`update()`, `delete()`, and transaction helpers.

## Rows

A row represents one database record. Extend `Frootbox\Db\Row` for your entities:

```php
namespace App\Persistence;

use Frootbox\Db\Row;

class Page extends Row
{
    protected $table = 'pages';
    protected $model = Pages::class;

    public function getTitle(): ?string
    {
        return $this->getAttribute('title');
    }

    public function setTitle(?string $title): static
    {
        return $this->setAttribute('title', $title);
    }

    public function isVisible(): bool
    {
        return !empty($this->getAttribute('isVisible'));
    }
}
```

Rows store their raw database values internally and track changed attributes.
Call `save()` to persist changed values and `delete()` to remove the record.

```php
$page->setTitle('About us');
$page->save();
```

### Legacy Dynamic Accessors

Older projects can still call accessors that are not explicitly defined:

```php
$page->getTitle();
$page->setTitle('About us');
$page->isVisible();
```

These calls are handled by `Row::__call()` for backward compatibility. New code
should add explicit methods that delegate to `getAttribute()` and
`setAttribute()`. Deprecated dynamic calls are logged once per call site by
default.

Disable that logging when needed:

```php
\Frootbox\Db\Row::setDeprecatedMagicCallLogging(false);
```

## Models

A model acts as a repository for one table:

```php
namespace App\Persistence;

use Frootbox\Db\Model;

class Pages extends Model
{
    protected string $table = 'pages';
    protected string $class = Page::class;
}
```

Fetch rows:

```php
$pages = new Pages($db);

$result = $pages->fetch([
    'where' => [
        'isVisible' => 1,
    ],
    'order' => [
        'title ASC',
    ],
]);

foreach ($result as $page) {
    echo $page->getTitle();
}
```

Fetch one row:

```php
$page = $pages->fetchById(42);
$latest = $pages->fetchOne([
    'order' => ['date DESC'],
]);
```

Insert a row:

```php
$page = new Page([
    'title' => 'About us',
    'isVisible' => 1,
]);

$pages->persist($page);
```

`insert()` is still available as a deprecated alias for `persist()`.

## Conditions

Condition objects can be used in `where` arrays for comparisons beyond equality:

```php
use Frootbox\Db\Conditions\Greater;
use Frootbox\Db\Conditions\Like;

$result = $pages->fetch([
    'where' => [
        new Greater('id', 10),
        new Like('title', '%About%'),
    ],
]);
```

Available conditions include:

- `Equal`
- `NotEqual`
- `Greater`
- `GreaterOrEqual`
- `Less`
- `LessOrEqual`
- `Like`
- `IsNull`
- `IsNotNull`
- `InArray`
- `NotInArray`
- `MatchColumn`

## Results

`Frootbox\Db\Result` is an iterator, `Countable`, and `JsonSerializable`.
It lazily converts raw database records into row objects while iterating.

Useful helpers:

```php
$result->getCount();
$result->isEmpty();
$result->getById('42');
$result->extractValues('title');
$result->implode(', ', 'title');
$result->removeByValue('status', 'archived');
```

## Nested Sets

The package includes optional nested-set base classes:

- `Frootbox\Db\Rows\NestedSet`
- `Frootbox\Db\Models\NestedSet`

They provide helpers such as `appendChild()`, `getChildren()`, `getParent()`,
`getTrace()`, `getSiblings()`, and `rewriteIds()`.

## Generating Explicit Accessors

The package ships a Composer binary:

```bash
vendor/bin/frootbox-db
```

Generate missing getter and setter methods for existing row classes:

```bash
vendor/bin/frootbox-db generate-accessors src/Persistence
```

When the command is run from a project root containing `localconfig.php`, it
automatically reads the database connection from:

```php
return [
    'database' => [
        'dbms' => 'mysql',
        'host' => 'localhost',
        'user' => 'root',
        'password' => 'root',
        'schema' => 'app',
    ],
];
```

Use a custom config path when needed:

```bash
vendor/bin/frootbox-db generate-accessors src/Persistence --localconfig=config/localconfig.php
```

Alternatively, a bootstrap file can return a `Frootbox\Db\Db` instance, or an
array with a `db` key:

```php
<?php

return $container->get(\Frootbox\Db\Db::class);
```

You can also pass connection details directly:

```bash
vendor/bin/frootbox-db generate-accessors src/Persistence \
  --db-host=localhost \
  --db-schema=app \
  --db-user=app \
  --db-password=secret
```

Preview changes without writing files:

```bash
vendor/bin/frootbox-db generate-accessors src/Persistence --dry-run
```

The generator scans concrete classes extending `Frootbox\Db\Row`, reads their
`protected $table` value, loads columns from `INFORMATION_SCHEMA.COLUMNS`, and
adds missing methods. Existing methods are not overwritten.

## Transactions

Use `transactionStart()` and `transactionCommit()` around multi-step changes:

```php
$db->transactionStart();

$page->setTitle('New title');
$page->save();

$db->transactionCommit();
```

Call `rollback()` if a transaction should be reverted.

## Error Handling

`Model::persist()` maps common MySQL integrity errors to package exceptions:

- `UniqueConstraintViolationException`
- `ForeignKeyViolationException`
- `ConstraintViolationException`

All extend `Frootbox\Db\Exception\DatabaseException`.

## License

This package is licensed under the GPL-3.0-or-later license.
