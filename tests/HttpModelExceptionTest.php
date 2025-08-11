<?php

namespace Iafilin\EloquentHttpAdapter\Tests;

use Iafilin\EloquentHttpAdapter\Exceptions\HttpModelException;
use Orchestra\Testbench\TestCase;

class HttpModelExceptionTest extends TestCase
{
    public function test_exception_can_be_instantiated()
    {
        $exception = new HttpModelException('Test error');
        
        $this->assertInstanceOf(HttpModelException::class, $exception);
        $this->assertEquals('Test error', $exception->getMessage());
    }

    public function test_api_request_failed_exception()
    {
        $originalException = new \Exception('Network error');
        $exception = HttpModelException::apiRequestFailed('GET', '/api/test', $originalException);
        
        $this->assertInstanceOf(HttpModelException::class, $exception);
        $this->assertStringContainsString('API request failed: GET /api/test', $exception->getMessage());
        $this->assertSame($originalException, $exception->getPrevious());
    }

    public function test_invalid_response_format_exception()
    {
        $actualResponse = 'invalid json';
        $exception = HttpModelException::invalidResponseFormat('array', $actualResponse);
        
        $this->assertInstanceOf(HttpModelException::class, $exception);
        $this->assertStringContainsString('Invalid response format', $exception->getMessage());
        $this->assertStringContainsString('array', $exception->getMessage());
        $this->assertStringContainsString('invalid json', $exception->getMessage());
    }

    public function test_missing_id_exception()
    {
        $exception = HttpModelException::missingId();
        
        $this->assertInstanceOf(HttpModelException::class, $exception);
        $this->assertStringContainsString('Cannot perform operation: model ID is missing', $exception->getMessage());
    }

    public function test_exception_with_code_and_previous()
    {
        $previousException = new \Exception('Previous error');
        $exception = new HttpModelException('Test error', 500, $previousException);
        
        $this->assertEquals(500, $exception->getCode());
        $this->assertSame($previousException, $exception->getPrevious());
    }
}
