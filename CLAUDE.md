# Project instructions

Guidance for agents working in this repository. Detailed rules live under [`.claude/rules/`](.claude/rules/) (Saloon 4, PHPStan level 8, Laravel package conventions).

## Commands

```bash
composer test                              # Pest
composer test -- tests/Unit/SomeTest.php   # Single file
composer lint                              # Pint (check)
composer format                            # Pint (fix)
composer analyse                           # PHPStan (level 8)
```

## Required checks before finishing work

```bash
composer test
composer analyse
composer lint
```

If `composer lint` fails, run `composer format` and rerun `composer lint`.

## Architecture

This is a **Laravel package**: a Saloon 4 HTTP SDK that also functions as a Laravel database driver for Snowflake.

```
SnowflakeSdk → SnowflakeConnector → Request classes → Responses
                                                     ↓
                              SnowflakeConnection (Laravel DB driver)
```

- **`SnowflakeConnector`** — Saloon connector; base URL (`https://{account}.snowflakecomputing.com`), JWT auth via `JwtAuthenticator`, JSON headers, timeouts.
- **`SnowflakeSdk`** — Public entrypoint; `make()` for standalone use; `execute()` sends SQL statements and returns `SnowflakeResult`.
- **`src/Requests/`** — One class per Snowflake REST API endpoint (`ExecuteStatementRequest`, `GetStatementStatusRequest`, `GetStatementPartitionRequest`).
- **`src/Responses/`** — `SnowflakeResult` (query result wrapper) and `ResultSet` (lazy partition-loading iterator).
- **`src/Auth/`** — `JwtAuthenticator` (Saloon authenticator) wrapping `JwtTokenProvider` (RSA key-pair JWT generation).
- **`src/Connection/`** — Laravel database driver: `SnowflakeConnection` (extends `Illuminate\Database\Connection`), `SnowflakeDbConnector`.
- **`src/Query/`** — Snowflake SQL grammar and processor.
- **`src/Schema/`** — Snowflake DDL grammar, blueprint (VARIANT, TIMESTAMP_NTZ, clustering keys, etc.), schema builder.
- **`src/Eloquent/`** — `UsesSnowflake` trait for models (ULID keys, timestamp handling).
- **`src/Casts/`** — `VariantCast`, `SnowflakeTimestamp` for Eloquent attribute casting.
- **`src/Laravel/SnowflakeServiceProvider`** — Binds connector and SDK; publishes config; registers `snowflake` database driver.

## Adding an endpoint

1. Add a `Request` under `src/Requests/`.
2. Expose a method on `SnowflakeSdk` that sends the request and returns the result.
3. Cover with `MockClient` / `MockResponse` in tests. `tests/Pest.php` enables `Config::preventStrayRequests()`.

## Conventions

- PHP 8.4+, `declare(strict_types=1)` in all PHP files.
- Prefer explicit types; PHPStan level 8 must stay clean.
- Uses `laravel/pint` from `require-dev` via Composer scripts.
