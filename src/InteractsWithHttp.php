<?php

namespace Iafilin\EloquentHttpAdapter;

trait InteractsWithHttp
{
    public function newEloquentBuilder($query): HttpQueryBuilder
    {
        return new HttpQueryBuilder($query, static::httpClient());
    }

    private static function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return \Http::asJson()->baseUrl('/api');
    }

    public function delete(): ?bool
    {
        try {
            static::httpClient()->delete('/'.$this->id)->throw();

            return true;
        } catch (\Exception $exception) {
            report($exception);

            return null;
        }
    }

    public static function create(array $attributes = [])
    {
        return static::query()->hydrate([static::httpClient()->post('/', $attributes)->throw()->json()])->first();
    }

    public function update(array $attributes = [], array $options = [])
    {
        return static::query()->hydrate([static::httpClient()->put($this->id, $attributes)->throw()->json()])->first();
    }

    public function save(array $options = [])
    {
        return static::query()->hydrate([static::httpClient()->put($this->id, $this->attributesToArray())->throw()->json()])->first();
    }
}
