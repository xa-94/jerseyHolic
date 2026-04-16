<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

class BusinessException extends \RuntimeException
{
    protected ErrorCode $errorCode;

    public function __construct(ErrorCode $errorCode, ?string $message = null, ?\Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message ?? $errorCode->name, $errorCode->value, $previous);
    }

    public function getErrorCode(): ErrorCode
    {
        return $this->errorCode;
    }
}
