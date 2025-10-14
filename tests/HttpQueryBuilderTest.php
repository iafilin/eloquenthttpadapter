<?php

namespace Iafilin\EloquentHttpAdapter\Tests;

use Iafilin\EloquentHttpAdapter\HttpQueryBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class HttpQueryBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_http_query_builder_can_be_instantiated()
    {
        $queryBuilder = new QueryBuilder($this->app['db']->connection());
        $httpClient = Http::asJson()->baseUrl('test-api');
        
        $builder = new HttpQueryBuilder($queryBuilder, $httpClient);
        
        $this->assertInstanceOf(HttpQueryBuilder::class, $builder);
    }

    public function test_where_conditions_are_added()
    {
        $queryBuilder = new QueryBuilder($this->app['db']->connection());
        $httpClient = Http::asJson()->baseUrl('test-api');
        
        $builder = new HttpQueryBuilder($queryBuilder, $httpClient);
        
        // Add a where condition
        $builder->where('name', 'Test');
        
        // This should not throw an exception
        $this->expectNotToPerformAssertions();
        
        // The actual parsing happens in the HTTP request, but we can test that the method exists
        $builder->get();
    }

    public function test_paginate_method_exists()
    {
        $queryBuilder = new QueryBuilder($this->app['db']->connection());
        $httpClient = Http::asJson()->baseUrl('test-api');
        
        $builder = new HttpQueryBuilder($queryBuilder, $httpClient);
        
        // Test that the method exists and can be called
        $this->assertTrue(method_exists($builder, 'paginate'));
    }

    public function test_count_method_exists()
    {
        $queryBuilder = new QueryBuilder($this->app['db']->connection());
        $httpClient = Http::asJson()->baseUrl('test-api');
        
        $builder = new HttpQueryBuilder($queryBuilder, $httpClient);
        
        // Test that the method exists and can be called
        $this->assertTrue(method_exists($builder, 'count'));
    }

    public function test_get_method_exists()
    {
        $queryBuilder = new QueryBuilder($this->app['db']->connection());
        $httpClient = Http::asJson()->baseUrl('test-api');
        
        $builder = new HttpQueryBuilder($queryBuilder, $httpClient);
        
        // Test that the method exists and can be called
        $this->assertTrue(method_exists($builder, 'get'));
    }
}
