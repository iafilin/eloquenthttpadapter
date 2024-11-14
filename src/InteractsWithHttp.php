<?php

namespace Iafilin\EloquentHttpAdapter;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait InteractsWithHttp
{
    /**
     * Override the Eloquent query builder with HttpQueryBuilder.
     *
     * @param mixed $query
     * @return HttpQueryBuilder
     */
    public function newEloquentBuilder($query): HttpQueryBuilder
    {
        return new HttpQueryBuilder($query, static::httpClient());
    }

    /**
     * Initialize the HTTP client for API requests.
     *
     * @return PendingRequest
     */
    private static function httpClient(): PendingRequest
    {
        return Http::asJson()->baseUrl(static::$apiEndpoint ?? '/api');
    }

    /**
     * Delete the model via API.
     *
     * @return bool|null
     */
    public function delete(): ?bool
    {
        try {
            static::httpClient()->delete('/' . $this->id)->throw();
            return true;
        } catch (\Exception $exception) {
            report($exception);
            return null;
        }
    }

    /**
     * Create a new model instance via API.
     *
     * @param array $attributes
     * @return static|null
     */
    public static function create(array $attributes = []): ?self
    {
        try {
            $response = static::httpClient()->post('/', $attributes)->throw()->json();
            return static::query()->hydrate([$response])->first();
        } catch (\Exception $exception) {
            report($exception);
            return null;
        }
    }

    /**
     * Update the model via API.
     *
     * @param array $attributes
     * @param array $options
     * @return static|null
     */
    public function update(array $attributes = [], array $options = []): ?self
    {
        try {
            $response = static::httpClient()->put('/' . $this->id, $attributes)->throw()->json();
            return static::query()->hydrate([$response])->first();
        } catch (\Exception $exception) {
            report($exception);
            return null;
        }
    }

    /**
     * Save the model via API.
     *
     * @param array $options
     * @return static|null
     */
    public function save(array $options = []): ?self
    {
        try {
            $response = static::httpClient()->put('/' . $this->id, $this->attributesToArray())->throw()->json();
            return static::query()->hydrate([$response])->first();
        } catch (\Exception $exception) {
            report($exception);
            return null;
        }
    }
}
