<?php

declare(strict_types=1);

namespace LaravelGtm\SnowflakeSdk\Exceptions;

use Exception;

class SnowflakeException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        protected ?string $sqlState = null,
        protected ?string $statementHandle = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getSqlState(): ?string
    {
        return $this->sqlState;
    }

    public function getStatementHandle(): ?string
    {
        return $this->statementHandle;
    }
}
