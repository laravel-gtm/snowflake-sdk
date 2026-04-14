<?php

declare(strict_types=1);

namespace LaravelGtm\SnowflakeSdk\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetStatementPartitionRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $statementHandle,
        private readonly int $partition,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/v2/statements/'.$this->statementHandle;
    }

    /**
     * @return array<string, int>
     */
    protected function defaultQuery(): array
    {
        return ['partition' => $this->partition];
    }
}
