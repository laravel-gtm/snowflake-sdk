<?php

declare(strict_types=1);

use LaravelGtm\SnowflakeSdk\Exceptions\AuthenticationException;
use LaravelGtm\SnowflakeSdk\Exceptions\QueryException;
use LaravelGtm\SnowflakeSdk\Exceptions\SnowflakeException;
use LaravelGtm\SnowflakeSdk\Requests\CancelStatementRequest;
use LaravelGtm\SnowflakeSdk\Requests\ExecuteStatementRequest;
use LaravelGtm\SnowflakeSdk\Requests\GetStatementStatusRequest;
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

    it('uses a per-call timeout override for the statement request and polling deadline', function () {
        $connector = new SnowflakeConnector(
            account: 'test-account',
            token: 'test-bearer-token',
        );
        $sdk = new class(connector: $connector, typeConverter: new TypeConverter, config: ['timeout' => 30, 'async_polling_interval' => 6000]) extends SnowflakeSdk
        {
            public int $sleptMilliseconds = 0;

            private float $testTime = 0.0;

            protected function currentTime(): float
            {
                return $this->testTime;
            }

            protected function sleep(int $milliseconds): void
            {
                $this->sleptMilliseconds += $milliseconds;
                $this->testTime += $milliseconds / 1000;
            }
        };
        $mockClient = new MockClient([
            MockResponse::make([
                'statementHandle' => 'handle-override',
                'statementStatusUrl' => '/api/v2/statements/handle-override',
            ], 202),
            MockResponse::make([
                'statementHandle' => 'handle-override',
                'statementStatusUrl' => '/api/v2/statements/handle-override',
            ], 202),
            MockResponse::make([], 200),
        ]);

        $connector->withMockClient($mockClient);

        expect(fn () => $sdk->execute('SELECT 1', context: ['timeout' => 7]))
            ->toThrow(SnowflakeException::class, 'Query timed out after 7 seconds');

        $response = $mockClient->findResponseByRequest(ExecuteStatementRequest::class);
        $body = $response?->getPendingRequest()->body()?->all();

        expect($sdk->sleptMilliseconds)->toBe(7000);
        expect($body)->toHaveKey('timeout', 7);
        $mockClient->assertSent(CancelStatementRequest::class);
    });

    it('cancels an asynchronous statement when its configured deadline is reached', function () {
        $connector = new SnowflakeConnector(
            account: 'test-account',
            token: 'test-bearer-token',
        );
        $sdk = new class(connector: $connector, typeConverter: new TypeConverter, config: ['database' => 'TEST_DB', 'schema' => 'PUBLIC', 'warehouse' => 'TEST_WH', 'timeout' => 1, 'async_polling_interval' => 600]) extends SnowflakeSdk
        {
            public int $sleptMilliseconds = 0;

            private float $testTime = 0.0;

            protected function currentTime(): float
            {
                return $this->testTime;
            }

            protected function sleep(int $milliseconds): void
            {
                $this->sleptMilliseconds += $milliseconds;
                $this->testTime += $milliseconds / 1000;
            }
        };

        $mockClient = new MockClient([
            MockResponse::make([
                'statementHandle' => 'handle-timeout',
                'statementStatusUrl' => '/api/v2/statements/handle-timeout',
            ], 202),
            MockResponse::make([
                'statementHandle' => 'handle-timeout',
                'statementStatusUrl' => '/api/v2/statements/handle-timeout',
            ], 202),
            MockResponse::make([], 200),
        ]);

        $connector->withMockClient($mockClient);

        expect(fn () => $sdk->execute('SELECT SYSTEM$WAIT(10)'))
            ->toThrow(SnowflakeException::class, 'Query timed out after 1 second');

        expect($sdk->sleptMilliseconds)->toBe(1000);
        $mockClient->assertSentInOrder([
            ExecuteStatementRequest::class,
            GetStatementStatusRequest::class,
            CancelStatementRequest::class,
        ]);

        $response = $mockClient->findResponseByRequest(ExecuteStatementRequest::class);
        $body = $response?->getPendingRequest()->body()?->all();

        expect($body)->toHaveKey('timeout', 1);
    });

    it('does not apply a polling deadline when timeout is zero', function () {
        $connector = new SnowflakeConnector(
            account: 'test-account',
            token: 'test-bearer-token',
        );
        $sdk = new class(connector: $connector, typeConverter: new TypeConverter, config: ['timeout' => 0, 'async_polling_interval' => 1]) extends SnowflakeSdk
        {
            protected function currentTime(): float
            {
                return PHP_FLOAT_MAX;
            }

            protected function sleep(int $milliseconds): void {}
        };

        $mockClient = new MockClient([
            MockResponse::make([
                'statementHandle' => 'handle-no-timeout',
                'statementStatusUrl' => '/api/v2/statements/handle-no-timeout',
            ], 202),
            MockResponse::make([
                'statementHandle' => 'handle-no-timeout',
                'resultSetMetaData' => ['numRows' => 1, 'rowType' => [], 'partitionInfo' => []],
                'data' => [[]],
            ], 200),
        ]);

        $connector->withMockClient($mockClient);

        expect($sdk->execute('SELECT 1')->getRowCount())->toBe(1);
        $mockClient->assertNotSent(CancelStatementRequest::class);
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
