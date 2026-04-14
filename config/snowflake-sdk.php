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

    'auth' => [
        'jwt' => [
            'user' => env('SNOWFLAKE_USER'),
            'private_key_path' => env('SNOWFLAKE_PRIVATE_KEY_PATH'),
            'private_key' => env('SNOWFLAKE_PRIVATE_KEY'),
            'private_key_passphrase' => env('SNOWFLAKE_PRIVATE_KEY_PASSPHRASE'),
        ],
    ],
];
