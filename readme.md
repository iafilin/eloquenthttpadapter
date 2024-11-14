**Buy Me a Coffee ☕️**

If you find this project helpful, consider supporting me by buying me a coffee!

[Buy me a coffee](https://www.donationalerts.com/r/iafilin)

Thank you! 🙏



# EloquentHttpAdapter

EloquentHttpAdapter is a Laravel package that allows you to work with RESTful API data using an Eloquent-like syntax. This package makes it easy to perform HTTP requests and handle responses as if working with a standard Eloquent model.

## Installation

To install the package, you can add it to your project via Composer:

```bash
composer require iafilin/eloquent-http-adapter
```

## Usage

### Setting Up the Model

To enable HTTP interactions on your model, use the `InteractsWithHttp` trait and define an `httpClient` method that sets the API endpoint for the model. Here’s an example:

```php
namespace App\Models;

use Iafilin\EloquentHttpAdapter\InteractsWithHttp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;

class Purchase extends Model
{
    use InteractsWithHttp;

    /**
     * Set up the HTTP client with the API endpoint.
     */
    private static function httpClient(): PendingRequest
    {
        return \Http::baseUrl('/api/admin/purchases');
    }
}
```

### API Interface Requirements

For the EloquentHttpAdapter package to interact effectively, the external API should follow these guidelines:

- **Standard REST Endpoints**:
    - `POST /api/resource` for creating records.
    - `GET /api/resource` for retrieving all records or paginated results.
    - `GET /api/resource/{id}` for retrieving a specific record.
    - `PUT /api/resource/{id}` for updating a specific record.
    - `DELETE /api/resource/{id}` for deleting a specific record.

- **Pagination**:
    - The API should support pagination parameters, typically `page` and `per_page`.
    - Response should include `data` (array of results), `total` (total items), `per_page` (items per page), and optionally `current_page`.

- **Filtering and Sorting**:
    - For filtering, the API should accept `filter[column_name]=value` parameters.
    - For sorting, the API should support a `sort` parameter, where `sort=column_name` sorts in ascending order and `sort=-column_name` in descending order.

- **Response Structure**:
    - Responses should be in JSON format.
    - For lists, JSON should include a `data` key with an array of records.
    - For individual records, JSON should directly contain the record’s fields.

Example of a paginated response structure:
```json
{
    "data": [
        { "id": 1, "attribute": "value" },
        { "id": 2, "attribute": "value" }
    ],
    "total": 50,
    "per_page": 15,
    "current_page": 1
}
```

### CRUD Operations

You can now use standard Eloquent methods on `Purchase` to interact with your API:

- **Create a record:**

    ```php
    $purchase = Purchase::create([
        'attribute' => 'value',
        // Other attributes...
    ]);
    ```

- **Read records:**

    ```php
    // Fetch all records
    $purchases = Purchase::all();

    // Fetch records with pagination
    $purchases = Purchase::paginate(15);
    ```

- **Update a record:**

    ```php
    $purchase = Purchase::find(1);
    $purchase->update([
        'attribute' => 'new value',
        // Other attributes...
    ]);
    ```

- **Delete a record:**

    ```php
    $purchase = Purchase::find(1);
    $purchase->delete();
    ```

### Integration with Spatie QueryBuilder

Currently, EloquentHttpAdapter supports interaction with **Spatie QueryBuilder** for flexible filtering, sorting, and includes. This enables you to leverage the power of Spatie's QueryBuilder when working with external API data.

For example:

```php
use Spatie\QueryBuilder\QueryBuilder;

$purchases = QueryBuilder::for(Purchase::class)
    ->allowedFilters(['status', 'created_at'])
    ->allowedSorts(['created_at', 'total'])
    ->allowedIncludes(['items'])
    ->paginate(15);
```

### Query Builder Features

You can use common Eloquent methods for filtering, sorting, and eager loading:

- **Filter and sort data:**

    ```php
    $purchases = Purchase::where('status', 'active')
        ->orderBy('created_at', 'desc')
        ->get();
    ```

- **Paginate results:**

    ```php
    $purchases = Purchase::paginate(10);
    ```

### Customization

You can customize the HTTP client setup by overriding the `httpClient` method in your model and specifying different base URLs, headers, or configurations.

## Advanced Usage

If you need advanced functionality, you can directly call the `httpClient` in your model to make custom HTTP requests, like adding headers or specific query parameters.

## Error Handling

This package handles errors by logging exceptions during API requests. If an error occurs, it will return `null` instead of throwing an exception, allowing your application to handle failures gracefully.

## License

This package is open-source and licensed under the [MIT license](LICENSE).
