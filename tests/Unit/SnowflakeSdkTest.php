<?php

declare(strict_types=1);

use LaravelGtm\SnowflakeSdk\Exceptions\AuthenticationException;
use LaravelGtm\SnowflakeSdk\Exceptions\QueryException;
use LaravelGtm\SnowflakeSdk\Exceptions\SnowflakeException;
use LaravelGtm\SnowflakeSdk\Requests\ExecuteStatementRequest;
use LaravelGtm\SnowflakeSdk\Responses\SnowflakeResult;
use LaravelGtm\SnowflakeSdk\SnowflakeConnector;
use LaravelGtm\SnowflakeSdk\SnowflakeSdk;
use LaravelGtm\SnowflakeSdk\Support\TypeConverter;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

describe('SnowflakeSdk', function () {
    beforeEach(function () {
        $this->connector = new SnowflakeConnector(
            account: 'test-account',
            token: 'test-bearer-token',
        );

        $this->sdk = new SnowflakeSdk(
            connector: $this->connector,
            typeConverter: new TypeConverter,
            config: [
                'database' => 'TEST_DB',
                'schema' => 'PUBLIC',
                'warehouse' => 'TEST_WH',
                'role' => 'SYSADMIN',
            ],
        );
    });

    it('executes a statement and returns a result', function () {
        $mockClient = new MockClient([
            ExecuteStatementRequest::class => MockResponse::make([
                'statementHandle' => 'handle-123',
                'resultSetMetaData' => [
                    'numRows' => 1,
                    'rowType' => [
                        ['name' => 'ID', 'type' => 'FIXED', 'scale' => 0],
                        ['name' => 'NAME', 'type' => 'TEXT'],
                    ],
                    'partitionInfo' => [],
                ],
                'data' => [['1', 'Alice']],
            ], 200),
        ]);

        $this->connector->withMockClient($mockClient);

        $result = $this->sdk->execute('SELECT * FROM users WHERE id = ?', [1]);

        expect($result)->toBeInstanceOf(SnowflakeResult::class);
        expect($result->getRowCount())->toBe(1);

        $rows = $result->fetchAll();
        expect($rows)->toHaveCount(1);
        expect($rows[0]->ID)->toBe(1);
        expect($rows[0]->NAME)->toBe('Alice');

        $mockClient->assertSent(ExecuteStatementRequest::class);
    });

    it('throws AuthenticationException on 401', function () {
        $mockClient = new MockClient([
            ExecuteStatementRequest::class => MockResponse::make([
                'message' => 'JWT token is invalid',
            ], 401),
        ]);

        $this->connector->withMockClient($mockClient);

        expect(fn () => $this->sdk->execute('SELECT 1'))
            ->toThrow(AuthenticationException::class, 'JWT token is invalid');
    });

    it('throws QueryException on 422', function () {
        $mockClient = new MockClient([
            ExecuteStatementRequest::class => MockResponse::make([
                'message' => 'SQL compilation error',
                'code' => '000904',
                'sqlState' => '42000',
            ], 422),
        ]);

        $this->connector->withMockClient($mockClient);

        expect(fn () => $this->sdk->execute('SELECT * FROM nonexistent'))
            ->toThrow(QueryException::class, 'SQL compilation error');
    });

    it('throws SnowflakeException on other errors', function () {
        $mockClient = new MockClient([
            ExecuteStatementRequest::class => MockResponse::make([
                'message' => 'Internal server error',
            ], 500),
        ]);

        $this->connector->withMockClient($mockClient);

        expect(fn () => $this->sdk->execute('SELECT 1'))
            ->toThrow(SnowflakeException::class, 'Internal server error');
    });

    it('creates via static make factory', function () {
        $sdk = SnowflakeSdk::make([
            'account' => 'test-account',
            'bearer_token' => 'test-bearer-token',
        ]);

        expect($sdk)->toBeInstanceOf(SnowflakeSdk::class);
        expect($sdk->getConnector())->toBeInstanceOf(SnowflakeConnector::class);
    });

    it('throws when account is missing from make()', function () {
        expect(fn () => SnowflakeSdk::make([]))
            ->toThrow(SnowflakeException::class, 'account is required');
    });

    it('throws when bearer token is missing from make()', function () {
        expect(fn () => SnowflakeSdk::make(['account' => 'test']))
            ->toThrow(SnowflakeException::class, 'bearer token is required');
    });
});
