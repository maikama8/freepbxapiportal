<?php

namespace App\Exceptions;

use Exception;

class FreePBXApiException extends Exception
{
    protected array $context;

    public function __construct(string $message = "", int $code = 0, array $context = [], Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get additional context data
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get error details for API responses
     */
    public function getErrorDetails(): array
    {
        return [
            'error' => 'FREEPBX_API_ERROR',
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'context' => $this->context
        ];
    }
}