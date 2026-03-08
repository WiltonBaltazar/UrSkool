<?php

namespace App\Exceptions;

use Exception;

class LendaException extends Exception
{
    public function __construct(
        string $message = 'Business rule violation.',
        int $status = 400,
        protected string $errorCode = 'BUSINESS_RULE_VIOLATION',
        protected mixed $details = null,
        ?\Throwable $previous = null
    ) {
        $httpStatus = ($status >= 400 && $status < 600) ? $status : 400;

        parent::__construct($message, $httpStatus, $previous);
    }

    public function status(): int
    {
        return ($this->getCode() >= 400 && $this->getCode() < 600) ? $this->getCode() : 400;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function details(): mixed
    {
        return $this->details;
    }
}
