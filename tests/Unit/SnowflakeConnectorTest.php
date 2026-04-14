<?php

declare(strict_types=1);

use LaravelGtm\SnowflakeSdk\Auth\JwtAuthenticator;
use LaravelGtm\SnowflakeSdk\Auth\JwtTokenProvider;
use LaravelGtm\SnowflakeSdk\SnowflakeConnector;

describe('SnowflakeConnector', function () {
    beforeEach(function () {
        $this->tokenProvider = new JwtTokenProvider(
            account: 'test-account',
            user: 'test_user',
            privateKeyPath: __DIR__.'/../Fixtures/test_rsa_key.pem',
        );

        $this->connector = new SnowflakeConnector(
            account: 'xy12345.us-east-1',
            tokenProvider: $this->tokenProvider,
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

    it('uses JWT authenticator', function () {
        $auth = (new ReflectionMethod($this->connector, 'defaultAuth'))
            ->invoke($this->connector);

        expect($auth)->toBeInstanceOf(JwtAuthenticator::class);
    });

    it('accepts a custom timeout', function () {
        $connector = new SnowflakeConnector(
            account: 'test',
            tokenProvider: $this->tokenProvider,
            timeout: 30,
        );

        $ref = new ReflectionProperty($connector, 'requestTimeout');
        expect($ref->getValue($connector))->toBe(30);
    });
});
