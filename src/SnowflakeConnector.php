<?php

declare(strict_types=1);

namespace LaravelGtm\SnowflakeSdk;

use LaravelGtm\SnowflakeSdk\Auth\JwtAuthenticator;
use LaravelGtm\SnowflakeSdk\Auth\JwtTokenProvider;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\HasTimeout;

class SnowflakeConnector extends Connector
{
    use HasTimeout;

    protected int $connectTimeout = 10;

    protected int $requestTimeout = 0;

    public function __construct(
        private readonly string $account,
        private readonly JwtTokenProvider $tokenProvider,
        int $timeout = 0,
    ) {
        $this->requestTimeout = $timeout;
    }

    public function resolveBaseUrl(): string
    {
        return 'https://'.rtrim($this->account, '/').'.snowflakecomputing.com';
    }

    protected function defaultAuth(): JwtAuthenticator
    {
        return new JwtAuthenticator($this->tokenProvider);
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'LaravelGtm-SnowflakeSdk/1.0',
        ];
    }
}
