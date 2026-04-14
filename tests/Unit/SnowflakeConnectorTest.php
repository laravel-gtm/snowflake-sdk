<?php

declare(strict_types=1);

use LaravelGtm\SnowflakeSdk\SnowflakeConnector;
use Saloon\Http\Auth\TokenAuthenticator;

describe('SnowflakeConnector', function () {
    beforeEach(function () {
        $this->connector = new SnowflakeConnector(
            account: 'xy12345.us-east-1',
            token: 'test-bearer-token',
        );
    });

    it('resolves the correct base URL', function () {
        expect($this->connector->resolveBaseUrl())
            ->toBe('https://xy12345.us-east-1.snowflakecomputing.com');
    });

    it('sets default JSON headers', function () {
        $headers = (new ReflectionMethod($this->connector, 'defaultHeaders'))
            ->invoke($this->connector);

        expect($headers)->toHaveKey('Content-Type', 'application/json');
        expect($headers)->toHaveKey('Accept', 'application/json');
        expect($headers)->toHaveKey('User-Agent');
    });

    it('uses token authenticator', function () {
        $auth = (new ReflectionMethod($this->connector, 'defaultAuth'))
            ->invoke($this->connector);

        expect($auth)->toBeInstanceOf(TokenAuthenticator::class);
    });

    it('accepts a custom timeout', function () {
        $connector = new SnowflakeConnector(
            account: 'test',
            token: 'test-bearer-token',
            timeout: 30,
        );

        $ref = new ReflectionProperty($connector, 'requestTimeout');
        expect($ref->getValue($connector))->toBe(30);
    });
});
