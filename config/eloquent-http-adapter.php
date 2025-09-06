<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default HTTP Configuration
    |--------------------------------------------------------------------------
    |
    | This option controls the default HTTP configuration for the package.
    |
    */
    'defaults' => [
        'base_url' => '/api',
        'timeout' => 30,
        'retry_times' => 3,
        'retry_milliseconds' => 1000,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Configuration
    |--------------------------------------------------------------------------
    |
    | This option controls the pagination settings for HTTP models.
    |
    */
    'pagination' => [
        'max_per_page' => 1000,
        'default_per_page' => 15,
        'page_name' => 'page',
        'per_page_name' => 'per_page',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling Configuration
    |--------------------------------------------------------------------------
    |
    | This option controls how errors are handled by the package.
    |
    */
    'error_handling' => [
        'log_errors' => true,
        'throw_exceptions' => false,
        'return_null_on_error' => true,
        'log_level' => 'error',
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Builder Configuration
    |--------------------------------------------------------------------------
    |
    | This option controls the query builder behavior.
    |
    */
    'query_builder' => [
        'enable_eager_loading' => true,
        'enable_sorting' => true,
        'enable_filtering' => true,
        'max_nested_depth' => 5,
        'filter_prefix' => 'filter',
        'sort_prefix' => 'sort',
        'include_prefix' => 'include',
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Configuration
    |--------------------------------------------------------------------------
    |
    | This option controls the response keys for HTTP models.
    |
    */
    'response' => [
        'data_key' => 'data',
        'total_key' => 'total',
        'per_page_key' => 'per_page',
        'current_page_key' => 'current_page',
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Validation Configuration
    |--------------------------------------------------------------------------
    |
    | This option controls response validation settings.
    |
    */
    'response_validation' => [
        'validate_response_format' => true,
        'required_fields' => ['id'],
        'optional_fields' => ['created_at', 'updated_at'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | This option controls caching settings for HTTP requests.
    |
    */
    'cache' => [
        'enabled' => false,
        'ttl' => 300, // 5 minutes
        'prefix' => 'http_model',
    ],

    /*
    |--------------------------------------------------------------------------
    | Server-side Safelist Configuration
    |--------------------------------------------------------------------------
    |
    | These options restrict which filters/sorts/includes can be applied on
    | the API server to protect against over-fetching and unwanted access.
    |
    */
    'server' => [
        'allowed_filters' => [],
        'allowed_sorts' => [],
        'allowed_includes' => [],
        'between_columns' => [],
        'infer_between' => true,
    ],
];
