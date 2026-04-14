<?php

declare(strict_types=1);

namespace LaravelGtm\SnowflakeSdk\Responses;

use Closure;
use Generator;
use IteratorAggregate;
use LaravelGtm\SnowflakeSdk\Support\TypeConverter;
use Traversable;

/**
 * @implements IteratorAggregate<int, object>
 */
final class ResultSet implements IteratorAggregate
{
    private readonly TypeConverter $typeConverter;

    /**
     * @param  array<int, array<int, mixed>>  $initialData
     * @param  array<int, array<string, mixed>>  $columnMeta
     * @param  array<int, array<string, mixed>>  $partitionInfo
     */
    public function __construct(
        private readonly array $initialData,
        private readonly array $columnMeta,
        private readonly array $partitionInfo,
        private readonly string $statementHandle,
        private readonly Closure $partitionFetcher,
    ) {
        $this->typeConverter = new TypeConverter;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getColumns(): array
    {
        return $this->columnMeta;
    }

    /**
     * @return array<int, string>
     */
    public function getColumnNames(): array
    {
        return array_map(fn (array $col): string => (string) $col['name'], $this->columnMeta);
    }

    public function getPartitionCount(): int
    {
        return max(1, count($this->partitionInfo));
    }

    /**
     * @return Traversable<int, object>
     */
    public function getIterator(): Traversable
    {
        return $this->rows();
    }

    /**
     * @return Generator<int, object>
     */
    public function rows(): Generator
    {
        $rowIndex = 0;

        foreach ($this->initialData as $row) {
            yield $rowIndex++ => $this->transformRow($row);
        }

        for ($i = 1; $i < $this->getPartitionCount(); $i++) {
            /** @var array<int, array<int, mixed>> $partitionData */
            $partitionData = ($this->partitionFetcher)($this->statementHandle, $i);

            foreach ($partitionData as $row) {
                yield $rowIndex++ => $this->transformRow($row);
            }
        }
    }

    /**
     * @return array<int, object>
     */
    public function toArray(): array
    {
        return iterator_to_array($this->rows(), false);
    }

    public function first(): ?object
    {
        foreach ($this->rows() as $row) {
            return $row;
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function transformRow(array $row): object
    {
        $result = new \stdClass;

        foreach ($this->columnMeta as $index => $column) {
            $name = (string) $column['name'];
            $type = (string) $column['type'];
            $rawValue = $row[$index] ?? null;

            $result->{$name} = $this->typeConverter->cast($rawValue, $type, $column);
        }

        return $result;
    }
}
