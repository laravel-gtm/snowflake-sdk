<?php

declare(strict_types=1);

namespace LaravelGtm\SnowflakeSdk\Responses;

use Closure;

final class SnowflakeResult
{
    private ?ResultSet $resultSet = null;

    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        private readonly array $response,
        private readonly Closure $partitionFetcher,
    ) {}

    public function getStatementHandle(): string
    {
        return (string) ($this->response['statementHandle'] ?? '');
    }

    public function getRowCount(): int
    {
        return (int) ($this->response['resultSetMetaData']['numRows'] ?? 0);
    }

    public function getRowsReturnedInFirstPartition(): int
    {
        /** @var array<int, mixed> $data */
        $data = $this->response['data'] ?? [];

        return count($data);
    }

    public function hasRows(): bool
    {
        return $this->getRowCount() > 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getColumnMeta(): array
    {
        /** @var array<int, array<string, mixed>> */
        return $this->response['resultSetMetaData']['rowType'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPartitionInfo(): array
    {
        /** @var array<int, array<string, mixed>> */
        return $this->response['resultSetMetaData']['partitionInfo'] ?? [];
    }

    public function getPartitionCount(): int
    {
        $partitionInfo = $this->getPartitionInfo();

        return $partitionInfo === [] ? 1 : count($partitionInfo);
    }

    public function getResultSet(): ResultSet
    {
        if ($this->resultSet === null) {
            $this->resultSet = new ResultSet(
                initialData: $this->response['data'] ?? [],
                columnMeta: $this->getColumnMeta(),
                partitionInfo: $this->getPartitionInfo(),
                statementHandle: $this->getStatementHandle(),
                partitionFetcher: $this->partitionFetcher,
            );
        }

        return $this->resultSet;
    }

    /**
     * @return array<int, object>
     */
    public function fetchAll(): array
    {
        return $this->getResultSet()->toArray();
    }

    public function fetchOne(): ?object
    {
        return $this->getResultSet()->first();
    }

    /**
     * @return array{rowCount: int, partitionCount: int, statementHandle: string}
     */
    public function getStats(): array
    {
        return [
            'rowCount' => $this->getRowCount(),
            'partitionCount' => $this->getPartitionCount(),
            'statementHandle' => $this->getStatementHandle(),
        ];
    }

    public function isSelectResult(): bool
    {
        return isset($this->response['data']) || isset($this->response['resultSetMetaData']['rowType']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawResponse(): array
    {
        return $this->response;
    }
}
