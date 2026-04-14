<?php

declare(strict_types=1);

namespace LaravelGtm\SnowflakeSdk\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class CancelStatementRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $statementHandle,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/v2/statements/'.$this->statementHandle.'/cancel';
    }
}
