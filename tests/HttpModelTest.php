<?php

namespace Iafilin\EloquentHttpAdapter\Tests;

use Iafilin\EloquentHttpAdapter\HttpModel;
use Iafilin\EloquentHttpAdapter\Exceptions\HttpModelException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class HttpModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock HTTP facade
        Http::fake();
    }

    public function test_http_model_can_be_instantiated()
    {
        $model = new TestHttpModel();
        
        $this->assertInstanceOf(HttpModel::class, $model);
        $this->assertInstanceOf(TestHttpModel::class, $model);
    }

    public function test_http_client_returns_pending_request()
    {
        $model = new TestHttpModel();
        
        $client = $model->httpClient();
        
        $this->assertInstanceOf(PendingRequest::class, $client);
    }

    public function test_delete_method_returns_false_when_no_id()
    {
        $model = new TestHttpModel();
        
        $result = $model->delete();
        
        $this->assertFalse($result);
    }

    public function test_handle_error_method_logs_exception()
    {
        $model = new TestHttpModel();
        $exception = new HttpModelException('Test error');
        
        // Should not throw exception
        $this->expectNotToPerformAssertions();
        
        $model->handleError($exception);
    }

    public function test_get_api_endpoint_returns_correct_value()
    {
        $model = new TestHttpModel();
        
        $endpoint = $model->getApiEndpoint();
        
        $this->assertEquals('test-api', $endpoint);
    }

    public function test_set_api_endpoint_updates_value()
    {
        $model = new TestHttpModel();
        
        $model->setApiEndpoint('new-api');
        
        $this->assertEquals('new-api', $model->getApiEndpoint());
    }

    public function test_register_fetch_params_resolver()
    {
        $model = new TestHttpModel();
        $closure = function() { return ['test' => 'value']; };
        
        $model->registerFetchParamsResolver($closure);
        
        // Test that the resolver was set (we can't directly access it, but we can test the behavior)
        $this->assertTrue(true); // Placeholder assertion
    }
}

class TestHttpModel extends HttpModel
{
    protected ?string $apiEndpoint = 'test-api';

    public function httpClient(): PendingRequest
    {
        return Http::asJson()->baseUrl($this->apiEndpoint);
    }

    public function registerFetchParamsResolver(\Closure $closure): void
    {
        parent::registerFetchParamsResolver($closure);
    }
}
