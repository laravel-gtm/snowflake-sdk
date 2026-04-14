<?php

declare(strict_types=1);

use LaravelGtm\SnowflakeSdk\Requests\CancelStatementRequest;
use LaravelGtm\SnowflakeSdk\Requests\ExecuteStatementRequest;
use LaravelGtm\SnowflakeSdk\Requests\GetStatementPartitionRequest;
use LaravelGtm\SnowflakeSdk\Requests\GetStatementStatusRequest;
use Saloon\Enums\Method;

describe('ExecuteStatementRequest', function () {
    it('uses POST method', function () {
        $request = new ExecuteStatementRequest('SELECT 1', [], 'req-123');

        expect($request->getMethod())->toBe(Method::POST);
    });

    it('resolves the correct endpoint', function () {
        $request = new ExecuteStatementRequest('SELECT 1', [], 'req-123');

        expect($request->resolveEndpoint())->toBe('/api/v2/statements');
    });

    it('includes requestId in query params', function () {
        $request = new ExecuteStatementRequest('SELECT 1', [], 'my-uuid');

        $query = (new ReflectionMethod($request, 'defaultQuery'))->invoke($request);

        expect($query)->toBe(['requestId' => 'my-uuid']);
    });

    it('builds the correct body payload', function () {
        $request = new ExecuteStatementRequest(
            sql: 'SELECT * FROM users',
            context: [
                'database' => 'MY_DB',
                'schema' => 'PUBLIC',
                'warehouse' => 'MY_WH',
                'role' => 'SYSADMIN',
            ],
            requestId: 'req-123',
            timeout: 30,
        );

        $body = (new ReflectionMethod($request, 'defaultBody'))->invoke($request);

        expect($body)->toBe([
            'statement' => 'SELECT * FROM users',
            'timeout' => 30,
            'database' => 'MY_DB',
            'schema' => 'PUBLIC',
            'warehouse' => 'MY_WH',
            'role' => 'SYSADMIN',
        ]);
    });

    it('omits role when empty', function () {
        $request = new ExecuteStatementRequest(
            sql: 'SELECT 1',
            context: [
                'database' => 'DB',
                'schema' => 'PUBLIC',
                'warehouse' => 'WH',
            ],
            requestId: 'req-123',
        );

        $body = (new ReflectionMethod($request, 'defaultBody'))->invoke($request);

        expect($body)->not->toHaveKey('role');
    });
});

describe('GetStatementStatusRequest', function () {
    it('uses GET method', function () {
        $request = new GetStatementStatusRequest('handle-abc');

        expect($request->getMethod())->toBe(Method::GET);
    });

    it('resolves endpoint with statement handle', function () {
        $request = new GetStatementStatusRequest('handle-abc');

        expect($request->resolveEndpoint())->toBe('/api/v2/statements/handle-abc');
    });
});

describe('GetStatementPartitionRequest', function () {
    it('uses GET method', function () {
        $request = new GetStatementPartitionRequest('handle-abc', 2);

        expect($request->getMethod())->toBe(Method::GET);
    });

    it('resolves endpoint with statement handle', function () {
        $request = new GetStatementPartitionRequest('handle-abc', 2);

        expect($request->resolveEndpoint())->toBe('/api/v2/statements/handle-abc');
    });

    it('includes partition in query params', function () {
        $request = new GetStatementPartitionRequest('handle-abc', 3);

        $query = (new ReflectionMethod($request, 'defaultQuery'))->invoke($request);

        expect($query)->toBe(['partition' => 3]);
    });
});

describe('CancelStatementRequest', function () {
    it('uses POST method', function () {
        $request = new CancelStatementRequest('handle-abc');

        expect($request->getMethod())->toBe(Method::POST);
    });

    it('resolves cancel endpoint', function () {
        $request = new CancelStatementRequest('handle-abc');

        expect($request->resolveEndpoint())->toBe('/api/v2/statements/handle-abc/cancel');
    });
});
