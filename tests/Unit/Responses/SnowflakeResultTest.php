<?php

declare(strict_types=1);

use LaravelGtm\SnowflakeSdk\Responses\SnowflakeResult;

describe('SnowflakeResult associative rows', function () {
    it('fetches associative rows with lowercase keys by default', function () {
        $result = new SnowflakeResult(
            response: [
                'statementHandle' => 'handle-123',
                'resultSetMetaData' => [
                    'numRows' => 1,
                    'rowType' => [
                        ['name' => 'ACCOUNT_ID', 'type' => 'FIXED', 'scale' => 0],
                        ['name' => 'ACCOUNT_NAME', 'type' => 'TEXT'],
                    ],
                    'partitionInfo' => [],
                ],
                'data' => [['42', 'Acme']],
            ],
            partitionFetcher: fn (string $handle, int $partition): array => [],
        );

        expect($result->fetchAssoc())->toBe([
            ['account_id' => 42, 'account_name' => 'Acme'],
        ]);
    });

    it('can preserve original column names in associative rows', function () {
        $result = new SnowflakeResult(
            response: [
                'statementHandle' => 'handle-123',
                'resultSetMetaData' => [
                    'numRows' => 1,
                    'rowType' => [
                        ['name' => 'ACCOUNT_ID', 'type' => 'FIXED', 'scale' => 0],
                    ],
                    'partitionInfo' => [],
                ],
                'data' => [['42']],
            ],
            partitionFetcher: fn (string $handle, int $partition): array => [],
        );

        expect($result->fetchAssoc(lowercaseKeys: false))->toBe([
            ['ACCOUNT_ID' => 42],
        ]);
    });

    it('streams associative rows across partitions', function () {
        $result = new SnowflakeResult(
            response: [
                'statementHandle' => 'handle-partitions',
                'resultSetMetaData' => [
                    'numRows' => 2,
                    'rowType' => [
                        ['name' => 'NAME', 'type' => 'TEXT'],
                    ],
                    'partitionInfo' => [[], []],
                ],
                'data' => [['Alice']],
            ],
            partitionFetcher: fn (string $handle, int $partition): array => [['Bob']],
        );

        expect(iterator_to_array($result->getResultSet()->assocRows(), false))->toBe([
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ]);
    });
});
