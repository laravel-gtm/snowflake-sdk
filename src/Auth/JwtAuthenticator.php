<?php

declare(strict_types=1);

namespace LaravelGtm\SnowflakeSdk\Auth;

use Saloon\Contracts\Authenticator;
use Saloon\Http\PendingRequest;

class JwtAuthenticator implements Authenticator
{
    public function __construct(
        private readonly JwtTokenProvider $tokenProvider,
    ) {}

    public function set(PendingRequest $pendingRequest): void
    {
        $pendingRequest->headers()->add('Authorization', 'Bearer '.$this->tokenProvider->getToken());
        $pendingRequest->headers()->add('X-Snowflake-Authorization-Token-Type', $this->tokenProvider->getTokenType());
    }
}
