<?php

declare(strict_types=1);

namespace LaravelGtm\SnowflakeSdk\Connection;

use Illuminate\Database\Connectors\ConnectorInterface;
use LaravelGtm\SnowflakeSdk\SnowflakeSdk;

class SnowflakeDbConnector implements ConnectorInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function connect(array $config): SnowflakeSdk
    {
        return SnowflakeSdk::make($config);
    }
}
