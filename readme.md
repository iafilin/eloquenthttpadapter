
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

## Usage

### Setting Up a Model

To enable API integration, use the `InteractsWithHttp` trait and define a custom `httpClient` method to configure the HTTP client:

```php
namespace App\Models;

use Iafilin\EloquentHttpAdapter\InteractsWithHttp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;

class Purchase extends Model
{
    use InteractsWithHttp;

    /**
     * Configure the HTTP client with the API endpoint.
     */
    private static function httpClient(): PendingRequest
    {
        return \Http::baseUrl('/api/admin/purchases');
    }
}
```

---

### Registering a Fetch Parameters Resolver

You can define the logic for building HTTP query parameters using the `registerFetchParamsResolver` method. This allows dynamic configuration of pagination, filtering, and sorting parameters.

Additionally, within the resolver, you have access to the `getQuery()` object, which lets you retrieve Eloquent `wheres` conditions and use them to generate HTTP parameters.

Example setup:
```php
class Purchase extends Model
{
    use InteractsWithHttp;

    protected static function boot()
    {
        parent::boot();

        static::registerFetchParamsResolver(function () {
            $params = [
                'page' => request()->query('page', 1),
                'per_page' => request()->query('per_page', 10),
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

## Customizing HTTP Requests

You can fully customize HTTP request behavior by overriding the `httpClient` method in your model. For example, adding headers or specific configurations:

```php
private static function httpClient(): PendingRequest
{
    return \Http::baseUrl('/api/admin/purchases')
                ->withHeaders(['Authorization' => 'Bearer token']);
}
```

---

If you have any questions or suggestions, feel free to open an issue or contribute! 💡

---

### Customizing API Fetch Parameters with `registerFetchParamsResolver`

The `registerFetchParamsResolver` method allows you to customize the HTTP query parameters sent to your API for fetching data. It provides a powerful way to dynamically build parameters like pagination, filters, and sorting based on the current Eloquent query or incoming user requests.

With access to the Eloquent query object (`$this->getQuery()`), you can parse `wheres`, `orders`, and other query conditions, transforming them into HTTP parameters compatible with your API.

#### Key Benefits:
- **Dynamic configuration:** Automatically adapt HTTP query parameters to match user input or specific query requirements.
- **Seamless integration:** Utilize existing Eloquent query methods while maintaining API compatibility.
- **Support for complex queries:** Build filters, pagination, and sorting logic based on both application and API needs.

#### Example Usage:
```php
class Purchase extends Model
{
    use InteractsWithHttp;

    protected static function boot()
    {
        parent::boot();

        static::registerFetchParamsResolver(function () {
            $params = [
                'page' => request()->query('page', 1),
                'per_page' => request()->query('per_page', 10),
            ];

            // Parse where conditions from the query object
            foreach ($this->getQuery()->wheres as $where) {
                $params["filter[{$where['column']}"]"] = $where['value'];
            }

            return $params;
        });
    }
}
```

This makes it easy to dynamically adapt HTTP query parameters based on user input or predefined conditions in your Laravel application.

## Error Handling

The package automatically logs errors during API interactions and returns `null` in case of failure. This ensures graceful handling of API downtime or errors without throwing exceptions.

---

## License

This package is open-source and licensed under the [MIT license](license.md).
