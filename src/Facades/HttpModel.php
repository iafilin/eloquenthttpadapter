<?php

namespace Iafilin\EloquentHttpAdapter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Iafilin\EloquentHttpAdapter\HttpModel make(string $modelClass)
 * @method static \Iafilin\EloquentHttpAdapter\HttpModel|null create(string $modelClass, array $attributes = [])
 * @method static bool delete(string $modelClass, $id)
 * @method static mixed find(string $modelClass, $id)
 * @method static mixed findOrFail(string $modelClass, $id)
 * @method static \Iafilin\EloquentHttpAdapter\HttpModel|null update(string $modelClass, $id, array $attributes = [])
 * @method static \Illuminate\Http\Client\PendingRequest client(string $modelClass)
 */
class HttpModel extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'http-model';
    }
}
