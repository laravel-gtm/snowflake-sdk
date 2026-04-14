# Snowflake SDK for Laravel

[![Tests](https://github.com/laravel-gtm/snowflake-sdk/actions/workflows/tests.yml/badge.svg)](https://github.com/laravel-gtm/snowflake-sdk/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/laravel-gtm/snowflake-sdk.svg?style=flat-square)](https://packagist.org/packages/laravel-gtm/snowflake-sdk)
[![License](https://img.shields.io/packagist/l/laravel-gtm/snowflake-sdk.svg?style=flat-square)](https://packagist.org/packages/laravel-gtm/snowflake-sdk)

A Laravel database driver for Snowflake using the REST SQL API and [Saloon](https://docs.saloon.dev). No PHP extensions or ODBC drivers required.

## Features

- Pure PHP implementation using Snowflake's REST API via Saloon v4
- Full Eloquent support with models and relationships
- Laravel Query Builder with Snowflake-specific SQL
- Migrations with Snowflake-specific column types
- ULID primary keys optimized for Snowflake clustering
- Native support for VARIANT, OBJECT, and ARRAY types
- Large result set streaming via partitions
- Bearer token authentication

## Requirements

- PHP 8.4+
- Laravel 11.0+, 12.0+, or 13.0+
- Snowflake account with REST API access

## Installation

```bash
composer require laravel-gtm/snowflake-sdk
```

The package will auto-register its service provider.

## Configuration

### 1. Publish the config file

```bash
php artisan vendor:publish --tag=snowflake-sdk-config
```

This creates `config/snowflake-sdk.php` with all available options.

### 2. Environment Variables

```env
SNOWFLAKE_ACCOUNT=your-account-identifier
SNOWFLAKE_BEARER_TOKEN=your-bearer-token
SNOWFLAKE_WAREHOUSE=COMPUTE_WH
SNOWFLAKE_DATABASE=MY_DATABASE
SNOWFLAKE_SCHEMA=PUBLIC
SNOWFLAKE_ROLE=SYSADMIN
```

### 3. Database Configuration

Add the Snowflake connection to `config/database.php`:

```php
'connections' => [
    'snowflake' => [
        'driver' => 'snowflake',
        'account' => env('SNOWFLAKE_ACCOUNT'),
        'bearer_token' => env('SNOWFLAKE_BEARER_TOKEN'),
        'warehouse' => env('SNOWFLAKE_WAREHOUSE'),
        'database' => env('SNOWFLAKE_DATABASE'),
        'schema' => env('SNOWFLAKE_SCHEMA', 'PUBLIC'),
        'role' => env('SNOWFLAKE_ROLE'),
    ],
],
```

## Usage

### Standalone SDK Usage

You can use the SDK directly without the database driver:

```php
use LaravelGtm\SnowflakeSdk\SnowflakeSdk;

// Via the container
$sdk = app(SnowflakeSdk::class);

// Or create standalone
$sdk = SnowflakeSdk::make([
    'account' => 'your-account',
    'bearer_token' => 'your-bearer-token',
]);

$result = $sdk->execute('SELECT * FROM my_table LIMIT 10');
```

### Eloquent Models

Add the `UsesSnowflake` trait to any model that connects to Snowflake:

```php
use Illuminate\Database\Eloquent\Model;
use LaravelGtm\SnowflakeSdk\Eloquent\Concerns\UsesSnowflake;

class User extends Model
{
    use UsesSnowflake;

    protected $connection = 'snowflake';
    protected $table = 'users';
}
```

The trait automatically generates ULID primary keys and handles Snowflake timestamp formats.

### Query Builder

```php
$users = DB::connection('snowflake')->table('users')->get();

DB::connection('snowflake')->table('users')->insert([
    'id' => Str::ulid()->toLower(),
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

DB::connection('snowflake')
    ->table('events')
    ->where('payload->type', 'purchase')
    ->get();
```

### Migrations

```php
use Illuminate\Database\Migrations\Migration;
use LaravelGtm\SnowflakeSdk\Schema\SnowflakeBlueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'snowflake';

    public function up(): void
    {
        Schema::connection('snowflake')->create('users', function (SnowflakeBlueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->variant('preferences');
            $table->timestamps();
            $table->clusterBy(['created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::connection('snowflake')->dropIfExists('users');
    }
};
```

### Snowflake Column Types

| Method | Snowflake Type |
|--------|---------------|
| `id()` | `CHAR(26)` |
| `variant()` | `VARIANT` |
| `object()` | `OBJECT` |
| `array()` | `ARRAY` |
| `geography()` | `GEOGRAPHY` |
| `geometry()` | `GEOMETRY` |
| `timestampNtz()` | `TIMESTAMP_NTZ` |
| `timestampLtz()` | `TIMESTAMP_LTZ` |
| `timestampTz()` | `TIMESTAMP_TZ` |
| `number()` | `NUMBER(p,s)` |
| `identity()` | `INTEGER IDENTITY` |

### Custom Casts

```php
use LaravelGtm\SnowflakeSdk\Casts\VariantCast;
use LaravelGtm\SnowflakeSdk\Casts\SnowflakeTimestamp;

class Event extends Model
{
    use UsesSnowflake;

    protected $connection = 'snowflake';

    protected $casts = [
        'payload' => VariantCast::class,
        'occurred_at' => SnowflakeTimestamp::class,
    ];
}
```

### Warehouse & Role Switching

```php
$connection = DB::connection('snowflake');

$connection->useWarehouse('ANALYTICS_WH');
$connection->useRole('ANALYST');
$connection->useSchema('STAGING');
```

### Transactions

```php
DB::connection('snowflake')->transaction(function ($db) {
    $db->table('accounts')->where('id', 1)->decrement('balance', 100);
    $db->table('accounts')->where('id', 2)->increment('balance', 100);
});
```

### Cursors

```php
foreach (DB::connection('snowflake')->table('events')->cursor() as $event) {
    // Process one row at a time
}
```

## Development

```bash
composer test        # Run tests (Pest)
composer analyse     # Run static analysis (PHPStan level 8)
composer lint        # Check code style (Pint)
composer format      # Fix code style (Pint)
```

## Limitations

- No savepoints (Snowflake limitation)
- No row locking (Snowflake is append-only)
- No traditional indexes (use clustering keys instead)

## License

MIT License. See [LICENSE](LICENSE) for details.
