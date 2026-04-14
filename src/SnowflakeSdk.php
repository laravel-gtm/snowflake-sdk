<?php

declare(strict_types=1);

namespace LaravelGtm\SnowflakeSdk;

use Closure;
use Illuminate\Support\Str;
use LaravelGtm\SnowflakeSdk\Auth\JwtTokenProvider;
use LaravelGtm\SnowflakeSdk\Exceptions\AuthenticationException;
use LaravelGtm\SnowflakeSdk\Exceptions\QueryException;
use LaravelGtm\SnowflakeSdk\Exceptions\SnowflakeException;
use LaravelGtm\SnowflakeSdk\Requests\CancelStatementRequest;
use LaravelGtm\SnowflakeSdk\Requests\ExecuteStatementRequest;
use LaravelGtm\SnowflakeSdk\Requests\GetStatementPartitionRequest;
use LaravelGtm\SnowflakeSdk\Requests\GetStatementStatusRequest;
use LaravelGtm\SnowflakeSdk\Responses\SnowflakeResult;
use LaravelGtm\SnowflakeSdk\Support\TypeConverter;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Response;

class SnowflakeSdk
{
    /** @var array<string, mixed> */
    private readonly array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly SnowflakeConnector $connector,
        private readonly TypeConverter $typeConverter,
        array $config = [],
    ) {
        $this->config = $config;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config): self
    {
        $account = $config['account'] ?? throw new SnowflakeException('Snowflake account is required');
        $tokenProvider = JwtTokenProvider::fromConfig($config);
        $timeout = (int) ($config['timeout'] ?? 0);

        $connector = new SnowflakeConnector((string) $account, $tokenProvider, $timeout);

        return new self($connector, new TypeConverter, $config);
    }

    /**
     * @param  array<int, mixed>  $bindings
     * @param  array<string, mixed>  $context
     */
    public function execute(string $sql, array $bindings = [], array $context = []): SnowflakeResult
    {
        $requestId = (string) Str::uuid();
        $interpolatedSql = $this->interpolateBindings($sql, $bindings);

        $mergedContext = [
            'database' => $context['database'] ?? $this->config['database'] ?? '',
            'schema' => $context['schema'] ?? $this->config['schema'] ?? 'PUBLIC',
            'warehouse' => $context['warehouse'] ?? $this->config['warehouse'] ?? '',
            'role' => $context['role'] ?? $this->config['role'] ?? null,
        ];

        $timeout = (int) ($this->config['timeout'] ?? 0);

        $request = new ExecuteStatementRequest($interpolatedSql, $mergedContext, $requestId, $timeout);

        try {
            $response = $this->connector->send($request);
        } catch (FatalRequestException $e) {
            throw new SnowflakeException('Failed to connect to Snowflake: '.$e->getMessage(), 0, $e);
        }

        return $this->handleResponse($response, $sql, $bindings);
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    private function handleResponse(
        Response $response,
        string $sql,
        array $bindings,
    ): SnowflakeResult {
        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        if ($response->status() === 401 || $response->status() === 403) {
            throw AuthenticationException::invalidCredentials((string) ($data['message'] ?? 'Authentication failed'));
        }

        if ($response->status() === 422) {
            throw QueryException::fromApiResponse($data, $sql, $bindings);
        }

        if (! $response->successful() && $response->status() !== 202) {
            throw new SnowflakeException(
                (string) ($data['message'] ?? 'Request failed with status '.$response->status()),
                $response->status(),
            );
        }

        if ($response->status() === 202 || isset($data['statementStatusUrl'])) {
            $handle = (string) ($data['statementHandle'] ?? '');

            return $this->pollForCompletion($handle, $sql, $bindings);
        }

        return new SnowflakeResult($data, $this->createPartitionFetcher());
    }

    private function pollForCompletion(string $statementHandle, string $sql, array $bindings): SnowflakeResult
    {
        $interval = (int) ($this->config['async_polling_interval'] ?? 500);
        $maxAttempts = 7200;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            usleep($interval * 1000);

            $request = new GetStatementStatusRequest($statementHandle);

            try {
                $response = $this->connector->send($request);
            } catch (FatalRequestException $e) {
                throw new SnowflakeException('Failed to poll Snowflake: '.$e->getMessage(), 0, $e);
            }

            /** @var array<string, mixed> $data */
            $data = $response->json() ?? [];

            if ($response->status() === 422) {
                throw QueryException::fromApiResponse($data, $sql, $bindings);
            }

            if (! $response->successful() && $response->status() !== 202) {
                throw new SnowflakeException(
                    (string) ($data['message'] ?? 'Request failed with status '.$response->status()),
                    $response->status(),
                );
            }

            if ($this->isQueryComplete($data)) {
                return new SnowflakeResult($data, $this->createPartitionFetcher());
            }
        }

        $this->cancelStatement($statementHandle);

        throw new SnowflakeException("Query timed out after polling for {$maxAttempts} attempts");
    }

    /**
     * @return array<int, mixed>
     */
    public function fetchPartition(string $statementHandle, int $partitionIndex): array
    {
        $request = new GetStatementPartitionRequest($statementHandle, $partitionIndex);

        try {
            $response = $this->connector->send($request);
        } catch (FatalRequestException $e) {
            throw new SnowflakeException('Failed to fetch partition: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new SnowflakeException("Failed to fetch partition {$partitionIndex}: {$response->status()}");
        }

        /** @var array<int, mixed> */
        return $response->json('data') ?? [];
    }

    public function cancelStatement(string $statementHandle): bool
    {
        try {
            $response = $this->connector->send(
                new CancelStatementRequest($statementHandle)
            );

            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    private function interpolateBindings(string $sql, array $bindings): string
    {
        if ($bindings === []) {
            return $sql;
        }

        $index = 0;

        return (string) preg_replace_callback('/\?/', function () use ($bindings, &$index) {
            /** @var int $index */
            $value = $bindings[$index++] ?? null;

            return $this->typeConverter->toSqlLiteral($value);
        }, $sql);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isQueryComplete(array $data): bool
    {
        if (isset($data['data'])) {
            return true;
        }

        $code = $data['code'] ?? null;

        return $code === '090001' || $code === 'success';
    }

    private function createPartitionFetcher(): Closure
    {
        return fn (string $handle, int $partition): array => $this->fetchPartition($handle, $partition);
    }

    public function getConnector(): SnowflakeConnector
    {
        return $this->connector;
    }
}
