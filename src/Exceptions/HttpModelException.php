<?php

namespace Iafilin\EloquentHttpAdapter\Exceptions;

use Exception;

class HttpModelException extends Exception
{
    /**
     * Create a new HTTP model exception.
     *
     * @param string $message
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct(string $message = '', int $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for API request failure.
     *
     * @param string $method
     * @param string $url
     * @param Exception $originalException
     * @return static
     */
    public static function apiRequestFailed(string $method, string $url, Exception $originalException): static
    {
        return new static(
            "API request failed: {$method} {$url} - {$originalException->getMessage()}",
            0,
            $originalException
        );
    }

    /**
     * Create an exception for invalid response format.
     *
     * @param string $expectedFormat
     * @param mixed $actualResponse
     * @return static
     */
    public static function invalidResponseFormat(string $expectedFormat, $actualResponse): static
    {
        return new static(
            "Invalid response format. Expected: {$expectedFormat}, Got: " . json_encode($actualResponse)
        );
    }

    /**
     * Create an exception for missing ID.
     *
     * @return static
     */
    public static function missingId(): static
    {
        return new static('Cannot perform operation: model ID is missing');
    }
}
