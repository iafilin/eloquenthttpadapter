## Donate: https://boosty.to/iafilin/donate


# EloquentHttpAdapter

**EloquentHttpAdapter** is a Laravel package that provides an alternative to [calebporzio/sushi](https://github.com/calebporzio/sushi). It allows you to work with RESTful API data using an Eloquent-like syntax.

This package was originally developed to integrate [Filament](https://filamentphp.com) with APIs, making it a convenient tool for admin panels. However, thanks to its flexibility, it can also be used for any task requiring API integration with an Eloquent-like interface.

---

## Installation

Install the package via Composer:

```bash
composer require iafilin/eloquenthttpadapter
```


---

## Quick start (TL;DR)

Use your Eloquent models on the client, and one helper on the server.

Client model (goes to API):

```php
class Purchase extends \Iafilin\EloquentHttpAdapter\HttpModel
{
    public function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return \Http::asJson()->baseUrl('/api/admin/purchases');
    }

    // Optional: short names in UI → relation paths
    public function getFilterAliases(): array
    {
        return ['name' => 'user.name', 'email' => 'user.email'];
    }
}
```

Server controller (applies params):

```php
use Iafilin\EloquentHttpAdapter\Server\HttpApiQuery;

public function index(\Illuminate\Http\Request $request)
{
    return HttpApiQuery::paginate(
        \App\Models\Purchase::query(),
        $request,
        allowedFilters: ['id','status','user.name','name'],
        allowedSorts: ['id','status','created_at'],
        allowedIncludes: ['user'],
        fieldAliases: ['name' => 'user.name'],
    );
}
```

That is enough to make Filament `searchable()`/`sortable()` columns, filters and includes work against your API.

---

## Examples

See the `examples/` directory for runnable snippets:

- Client usage: `examples/client/ModelExample.php`, `examples/client/UsageExample.php`
- Server (API) usage: `examples/server/UserController.php`, `examples/server/routes_example.php`

---

## Usage

### Setting Up a Model

Use the `HttpModel` abstract class for clean architecture and proper method overriding:

```php
namespace App\Models;

use Iafilin\EloquentHttpAdapter\HttpModel;
use Illuminate\Http\Client\PendingRequest;

class Purchase extends HttpModel
{
    protected ?string $apiEndpoint = '/api/admin/purchases';

    /**
     * Configure the HTTP client with the API endpoint.
     */
    public function httpClient(): PendingRequest
    {
        return \Http::asJson()
            ->baseUrl($this->apiEndpoint)
            ->withHeaders(['Authorization' => 'Bearer token']);
    }

    protected static function boot()
    {
        parent::boot();

        // Register fetch parameters resolver
        $instance = new static();
        $instance->registerFetchParamsResolver(function ($page, $perPage) {
            $params = [
                'page' => $page,
                'per_page' => $perPage,
            ];

            // Parse `wheres` from the query object
            foreach ($this->getQuery()->wheres as $where) {
                $params["filter[{$where['column']}"] = $where['value'];
            }

            return $params;
        });
    }
}
```

### Filtering, sorting, includes (client)

- Mark Filament columns as `searchable()`/`sortable()` or add `where`/`orderBy` to the query.
- The client builder converts them to HTTP params:
  - `filter[field]`, including relation paths like `user.name`
  - `sort=column,-other`
  - `include=rel1,rel2`
- For short names on the client, return aliases from your `HttpModel::getFilterAliases()`:

```php
class Purchase extends HttpModel
{
    public function getFilterAliases(): array
    {
        return [
            'name' => 'user.name',
            'email' => 'user.email',
            'tester_phone' => 'user.websso_id',
        ];
    }
}
```

### Using the Facade

You can also use the facade for convenient access:

```php
use Iafilin\EloquentHttpAdapter\Facades\HttpModel;

// Create a model instance
$model = HttpModel::make(Purchase::class);

// Find a record
$purchase = HttpModel::find(Purchase::class, 1);

// Create a record
$purchase = HttpModel::create(Purchase::class, ['name' => 'New Item']);
```

---

## Client-side relations (includes → relations)

You can hydrate included data into real Eloquent relations on the client.

1) Declare relation class mapping on your HTTP model:

```php
class Purchase extends HttpModel
{
    protected ?string $apiEndpoint = '/api/admin/purchases';

    // relation name => model class
    protected array $relationClassMap = [
        'user' => \App\Models\User::class,
        'items' => \App\Models\PurchaseItem::class,
    ];
}
```

2) Request relations via `with()` (becomes `include=` under the hood):

```php
$purchase = Purchase::with(['user','items'])->find(1);

// Access as normal Eloquent relations
$user = $purchase->user;                 // \App\Models\User
$items = $purchase->items;               // Illuminate\Database\Eloquent\Collection
```

3) Explicitly (lazy) load relations when needed:

```php
$purchase->load('user', 'items');
$purchase->loadMissing('items');
```

Note: the API must return included resources inline (side-loaded) under keys matching relation names.

---

### Custom Error Handling

You can customize error handling by overriding the `handleError` method:

```php
class Purchase extends HttpModel
{
    public function handleError(\Iafilin\EloquentHttpAdapter\Exceptions\HttpModelException $exception): void
    {
        // Custom error handling logic
        logger()->error('API Error', [
            'model' => static::class,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // You can also throw the exception if needed
        if (config('eloquent-http-adapter.error_handling.throw_exceptions', false)) {
            throw $exception;
        }
    }
}
```

---

### Registering a Fetch Parameters Resolver

You can define the logic for building HTTP query parameters using the `registerFetchParamsResolver` method. This allows dynamic configuration of pagination, filtering, and sorting parameters.

Additionally, within the resolver, you have access to the `getQuery()` object, which lets you retrieve Eloquent `wheres` conditions and use them to generate HTTP parameters.

Example setup:
```php
class Purchase extends HttpModel
{
    protected ?string $apiEndpoint = '/api/admin/purchases';

    protected static function boot()
    {
        parent::boot();

        $instance = new static();
        $instance->registerFetchParamsResolver(function ($page, $perPage) {
            $params = [
                'page' => $page,
                'per_page' => $perPage,
            ];

            // Parse `wheres` from the query object
            foreach ($this->getQuery()->wheres as $where) {
                $params["filter[{$where['column']}"] = $where['value'];
            }

            return $params;
        });
    }
}
```

---

## API Requirements

To ensure compatibility with the package, your API should follow these conventions:

1. **Standard REST endpoints:**
    - `POST /api/resource` — Create records.
    - `GET /api/resource` — Fetch records (paginated or full list).
    - `GET /api/resource/{id}` — Fetch a single record.
    - `PUT /api/resource/{id}` — Update a record.
    - `DELETE /api/resource/{id}` — Delete a record.

2. **Response Structure:**
    - **Lists:** Should include `data`, `total`, `per_page`, and `current_page`.
    - **Single Record:** Should return attributes directly without nesting.

Example paginated response:
```json
{
    "data": [
        { "id": 1, "name": "Item 1" },
        { "id": 2, "name": "Item 2" }
    ],
    "total": 50,
    "per_page": 10,
    "current_page": 1
}
```

3. **Pagination:**
    - Support for `page` and `per_page` query parameters.

4. **Filters and Sorting:**
    - Filters: `filter[column]=value`.
    - Sorting: `sort=column` for ascending and `sort=-column` for descending.

---

## Server-side (API) integration for Laravel

Use these helpers in your Laravel API to accept filter/sort/include/page params from the client and produce standardized responses.

### Option A: QueryApplier + HttpResponder (basic)

### 1) Configure allowlists (security)

Update `config/eloquent-http-adapter.php`:

```php
'server' => [
    'allowed_filters' => ['status','email','created_at'],
    'allowed_sorts' => ['name','created_at'],
    'allowed_includes' => ['roles','profile'],
    'between_columns' => ['created_at'],
    'infer_between' => true,
],
```

### 2) Apply query params and respond

```php
use Iafilin\EloquentHttpAdapter\Server\QueryApplier;
use Iafilin\EloquentHttpAdapter\Server\HttpResponder;
use Illuminate\Http\Request;

class UserController
{
    public function index(Request $request, QueryApplier $applier, HttpResponder $responder)
    {
        $builder = \App\Models\User::query();
        $builder = $applier->apply($request, $builder);

        // Return paginated or full collection (controlled by ?paginated=true|false)
        return $responder->paginated($request, $builder);
        // return $responder->collection($builder);
    }
}
```

### Option B: HttpApiQuery (one-call helper, relations & aliases)

If you prefer a one-liner with relation paths, aliases, composite filters and global search support, use:

```php
use Iafilin\EloquentHttpAdapter\Server\HttpApiQuery;

public function index(Request $request)
{
    $base = \App\Models\Purchase::query();
    return HttpApiQuery::paginate(
        $base,
        $request,
        allowedFilters: [
            'id','status','created_at','user.name','user.email','user.websso_id','goods.title',
            'name','email','tester_phone','goods_summary', // short aliases
        ],
        allowedSorts: ['id','created_at','status'],
        allowedIncludes: ['user','goods.specifications'],
        betweenFields: ['created_at'],
        fuzzyLikeFields: ['status','user.name','user.email','user.websso_id','goods.title'],
        searchableFields: ['status','user.name','user.email','user.websso_id','goods.title'],
        fieldAliases: [ 'name' => 'user.name', 'email' => 'user.email', 'tester_phone' => 'user.websso_id' ],
        compositeOrFilters: [ 'goods_summary' => ['goods.title','goods.specifications.size','goods.specifications.color'] ],
        defaultPerPage: 100,
    );
}
```

### 3) Accepted parameters
- Filters: `filter[column]=value` with operators
  - `=`, `!=` (via leading `!`), `in` (comma-separated or arrays), `between` (two comma-separated values), `>`, `<`, `>=`, `<=`, `like` (`*` → `%`, `?` → `_`)
- Sorting: `sort=column` for ASC, `sort=-column` for DESC
- Includes: `include=rel1,rel2`
- Pagination: `page`, `per_page`

The response structure matches the client expectations: `data`, `total`, `per_page`, `current_page`.

---

## CRUD Operations

Once your model is set up, you can use standard Eloquent methods to interact with your API:

### Create a Record
```php
$purchase = Purchase::create(['name' => 'New Item']);
```

### Read Records
```php
$purchases = Purchase::all(); // Fetch all records
$purchases = Purchase::paginate(10); // Paginate results
$purchase = Purchase::find(1); // Find single record
$purchase = Purchase::findOrFail(1); // Find or throw exception
```

### Update a Record
```php
$purchase = Purchase::find(1);
$purchase->update(['name' => 'Updated Name']);
```

### Delete a Record
```php
$purchase = Purchase::find(1);
$purchase->delete();
```

---

## Configuration

Publish the configuration file to customize the package behavior:

```bash
php artisan vendor:publish --tag=eloquent-http-adapter.config
```

### Available Configuration Options

```php
return [
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
    'pagination' => [
        'max_per_page' => 1000,
        'default_per_page' => 15,
        'page_name' => 'page',
        'per_page_name' => 'per_page',
    ],
    'error_handling' => [
        'log_errors' => true,
        'throw_exceptions' => false,
        'return_null_on_error' => true,
        'log_level' => 'error',
    ],
    'response_validation' => [
        'validate_response_format' => true,
        'required_fields' => ['id'],
        'optional_fields' => ['created_at', 'updated_at'],
    ],
];
```

---

## Customizing HTTP Requests

You can fully customize HTTP request behavior by overriding the `httpClient` method in your model. For example, adding headers or specific configurations:

```php
public function httpClient(): PendingRequest
{
    return \Http::asJson()
        ->baseUrl($this->apiEndpoint)
        ->withHeaders(['Authorization' => 'Bearer token'])
        ->timeout(60);
}
```

---

## Error Handling

The package automatically logs errors during API interactions and returns `null` in case of failure. This ensures graceful handling of API downtime or errors without throwing exceptions.

### Custom Error Handling

You can customize error handling by overriding methods in your model:

```php
class Purchase extends HttpModel
{
    public function handleError(\Iafilin\EloquentHttpAdapter\Exceptions\HttpModelException $exception): void
    {
        // Custom error handling logic
        logger()->error('API Error', [
            'model' => static::class,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

---

## Architecture Benefits

### Why HttpModel over traits?

- ✅ **Proper method overriding** - Correctly overrides Laravel Eloquent methods
- ✅ **Clean architecture** - Follows inheritance principles
- ✅ **Better testing** - Easier to mock and test
- ✅ **Type safety** - Better IDE support and type checking
- ✅ **Flexibility** - Easy to extend and customize
- ✅ **Interface support** - Implements HttpModelInterface for better design
- ✅ **Custom exceptions** - Specific exceptions for better error handling

### Example Architecture

```php
// ✅ Clean inheritance hierarchy
Model (Laravel) → HttpModel (HTTP logic) → Purchase (Business logic)

// ❌ Old trait approach (removed in v2.0)
Model + InteractsWithHttp (mixed concerns) → Purchase
```

---

## What's New in v2.1

- ✅ **HttpModelInterface** - Interface for better architecture and testing
- ✅ **Custom exceptions** - HttpModelException for better error handling
- ✅ **Enhanced error handling** - Configurable error handling with custom exceptions
- ✅ **Response validation** - Better validation of API responses
- ✅ **Facade support** - HttpModel facade for convenient access
- ✅ **Comprehensive tests** - Test suite for HttpModel functionality
- ✅ **Enhanced configuration** - More configuration options for customization
- ✅ **Better documentation** - Updated examples and usage patterns

---

If you have any questions or suggestions, feel free to open an issue or contribute! 💡
