<?php

declare(strict_types=1);

namespace LaravelGtm\SnowflakeSdk\Laravel;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use LaravelGtm\SnowflakeSdk\Auth\JwtTokenProvider;
use LaravelGtm\SnowflakeSdk\Connection\SnowflakeConnection;
use LaravelGtm\SnowflakeSdk\Connection\SnowflakeDbConnector;
use LaravelGtm\SnowflakeSdk\SnowflakeConnector;
use LaravelGtm\SnowflakeSdk\SnowflakeSdk;
use LaravelGtm\SnowflakeSdk\Support\TypeConverter;

class SnowflakeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/snowflake-sdk.php', 'snowflake-sdk');

        $this->app->singleton(SnowflakeConnector::class, function (): SnowflakeConnector {
            $configRepository = $this->app->make(ConfigRepository::class);
            /** @var array<string, mixed> $config */
            $config = (array) $configRepository->get('snowflake-sdk', []);

            $account = (string) ($config['account'] ?? '');
            $tokenProvider = JwtTokenProvider::fromConfig($config);
            $timeout = (int) ($config['timeout'] ?? 0);

            return new SnowflakeConnector($account, $tokenProvider, $timeout);
        });

        $this->app->singleton(SnowflakeSdk::class, function (): SnowflakeSdk {
            $configRepository = $this->app->make(ConfigRepository::class);
            /** @var array<string, mixed> $config */
            $config = (array) $configRepository->get('snowflake-sdk', []);

            return new SnowflakeSdk(
                $this->app->make(SnowflakeConnector::class),
                new TypeConverter,
                $config,
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/snowflake-sdk.php' => $this->app->configPath('snowflake-sdk.php'),
            ], 'snowflake-sdk-config');
        }

        Connection::resolverFor('snowflake', function ($connection, $database, $prefix, $config) {
            if ($connection instanceof \Closure) {
                $connection = $connection();
            }

            return new SnowflakeConnection($connection, $database, '', $config);
        });

        $this->app['db']->extend('snowflake', function ($config, $name) {
            $config['name'] = $name;

            $connector = new SnowflakeDbConnector;
            $client = $connector->connect($config);

            return new SnowflakeConnection($client, $config['database'] ?? '', '', $config);
        });
    }
}
