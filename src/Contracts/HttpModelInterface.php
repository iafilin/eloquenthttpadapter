<?php

namespace Iafilin\EloquentHttpAdapter\Contracts;

use Illuminate\Http\Client\PendingRequest;

interface HttpModelInterface
{
    /**
     * Get the HTTP client for API requests.
     *
     * @return PendingRequest
     */
    public function httpClient(): PendingRequest;

    /**
     * Delete the model via API.
     *
     * @return bool|null
     */
    public function delete(): ?bool;

    /**
     * Create a new model instance via API.
     *
     * @param array $attributes
     * @return static|null
     */
    public static function create(array $attributes = []): ?self;

    /**
     * Update the model via API.
     *
     * @param array $attributes
     * @param array $options
     * @return static|null
     */
    public function update(array $attributes = [], array $options = []): ?self;

    /**
     * Save the model via API.
     *
     * @param array $options
     * @return static|null
     */
    public function save(array $options = []): ?self;

    /**
     * Find a model by its primary key.
     *
     * @param mixed $id
     * @param array $columns
     * @return static|null
     */
    public static function find($id, $columns = ['*']);

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param mixed $id
     * @param array $columns
     * @return static
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findOrFail($id, $columns = ['*']);
}
