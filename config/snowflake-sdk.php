<?php

declare(strict_types=1);

return [
    'account' => env('SNOWFLAKE_ACCOUNT'),
    'warehouse' => env('SNOWFLAKE_WAREHOUSE'),
    'database' => env('SNOWFLAKE_DATABASE'),
    'schema' => env('SNOWFLAKE_SCHEMA', 'PUBLIC'),
    'role' => env('SNOWFLAKE_ROLE'),
    'timeout' => (int) env('SNOWFLAKE_TIMEOUT', 0),
    'async_polling_interval' => (int) env('SNOWFLAKE_POLLING_INTERVAL', 500),

    'bearer_token' => env('SNOWFLAKE_BEARER_TOKEN'),
];
