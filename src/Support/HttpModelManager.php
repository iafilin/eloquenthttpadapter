<?php

namespace Iafilin\EloquentHttpAdapter\Support;

use Illuminate\Http\Client\PendingRequest;
use Iafilin\EloquentHttpAdapter\HttpModel;

class HttpModelManager
{
    public function make(string $modelClass): HttpModel
    {
        return new $modelClass();
    }

    public function client(string $modelClass): PendingRequest
    {
        return (new $modelClass())->httpClient();
    }

    public function find(string $modelClass, $id)
    {
        /** @var HttpModel $modelClass */
        return $modelClass::find($id);
    }

    public function findOrFail(string $modelClass, $id)
    {
        /** @var HttpModel $modelClass */
        return $modelClass::findOrFail($id);
    }

    public function create(string $modelClass, array $attributes = [])
    {
        /** @var HttpModel $modelClass */
        return $modelClass::create($attributes);
    }

    public function update(string $modelClass, $id, array $attributes = [])
    {
        /** @var HttpModel $instance */
        $instance = new $modelClass();
        $instance->forceFill([$instance->getKeyName() => $id]);
        $instance->exists = true;
        return $instance->update($attributes);
    }

    public function delete(string $modelClass, $id): bool
    {
        /** @var HttpModel $instance */
        $instance = new $modelClass();
        $instance->forceFill([$instance->getKeyName() => $id]);
        $instance->exists = true;
        return (bool) $instance->delete();
    }
}
