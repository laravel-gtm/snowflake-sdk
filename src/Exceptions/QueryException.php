<?php

declare(strict_types=1);

namespace LaravelGtm\SnowflakeSdk\Exceptions;

class QueryException extends SnowflakeException
{
    /**
     * @param  array<int, mixed>  $bindings
     */
    public function __construct(
        string $message,
        protected string $sql,
        protected array $bindings = [],
        int $code = 0,
        ?\Exception $previous = null,
        ?string $sqlState = null,
        ?string $statementHandle = null,
    ) {
        parent::__construct($message, $code, $previous, $sqlState, $statementHandle);
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<int, mixed>  $bindings
     */
    public static function fromApiResponse(array $response, string $sql, array $bindings = []): self
    {
        $message = (string) ($response['message'] ?? 'Query execution failed');
        $code = isset($response['code']) ? (int) $response['code'] : 0;
        $sqlState = isset($response['sqlState']) ? (string) $response['sqlState'] : null;
        $statementHandle = isset($response['statementHandle']) ? (string) $response['statementHandle'] : null;

        return new self(
            message: $message,
            sql: $sql,
            bindings: $bindings,
            code: $code,
            sqlState: $sqlState,
            statementHandle: $statementHandle,
        );
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<int, mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function getFormattedMessage(): string
    {
        $message = $this->getMessage();

        if ($this->sqlState !== null) {
            $message .= " [SQLSTATE: {$this->sqlState}]";
        }

        $message .= "\n\nSQL: {$this->sql}";

        if ($this->bindings !== []) {
            $message .= "\n\nBindings: ".json_encode($this->bindings);
        }

        return $message;
    }
}
