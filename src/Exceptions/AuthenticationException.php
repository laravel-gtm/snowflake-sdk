<?php

declare(strict_types=1);

namespace LaravelGtm\SnowflakeSdk\Exceptions;

class AuthenticationException extends SnowflakeException
{
    public static function invalidCredentials(string $message = 'Invalid credentials'): self
    {
        return new self($message, 401);
    }
}
