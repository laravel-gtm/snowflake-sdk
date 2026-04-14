<?php

declare(strict_types=1);

namespace LaravelGtm\SnowflakeSdk\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class ExecuteStatementRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly string $sql,
        private readonly array $context,
        private readonly string $requestId,
        private readonly int $timeout = 0,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/v2/statements';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return ['requestId' => $this->requestId];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $payload = [
            'statement' => $this->sql,
            'timeout' => $this->timeout,
            'database' => $this->context['database'] ?? '',
            'schema' => $this->context['schema'] ?? 'PUBLIC',
            'warehouse' => $this->context['warehouse'] ?? '',
        ];

        $role = $this->context['role'] ?? null;
        if ($role !== null && $role !== '') {
            $payload['role'] = $role;
        }

        return $payload;
    }
}
