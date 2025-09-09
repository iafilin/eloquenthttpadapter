<?php

namespace Iafilin\EloquentHttpAdapter;

use Iafilin\EloquentHttpAdapter\Contracts\HttpModelInterface;
use Iafilin\EloquentHttpAdapter\Exceptions\HttpModelException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;

abstract class HttpModel extends Model implements HttpModelInterface
{
    protected static array $fetchParamsResolvers = [];

    protected ?string $apiEndpoint = null;

    /**
     * Map of relation name => related model class for include hydration.
     * Example: ['posts' => \App\Models\Post::class, 'user' => \App\Models\User::class]
     * When present in API response, these keys will be converted to loaded relations.
     */
    protected array $relationClassMap = [];

    /**
     * Initialize the HTTP client for API requests using config defaults.
     */
    public function httpClient(): PendingRequest
    {
        $defaults = config('eloquent-http-adapter.defaults', []);

        $client = Http::asJson()
            ->baseUrl($this->apiEndpoint ?? ($defaults['base_url'] ?? '/api'))
            ->timeout($defaults['timeout'] ?? 30);

        if (!empty($defaults['retry_times'])) {
            $client = $client->retry(
                (int) ($defaults['retry_times'] ?? 0),
                (int) ($defaults['retry_milliseconds'] ?? 0)
            );
        }

        if (!empty($defaults['headers']) && is_array($defaults['headers'])) {
            $client = $client->withHeaders($defaults['headers']);
        }

        return $client;
    }

    /**
     * Override the Eloquent query builder with HttpQueryBuilder.
     */
    public function newEloquentBuilder($query): HttpQueryBuilder
    {
        return new HttpQueryBuilder($query, $this->httpClient(), static::getFetchParamsResolver());
    }

    /**
     * Register a fetch parameters resolver (static per model class).
     */
    public static function registerFetchParamsResolver(\Closure $closure): void
    {
        static::$fetchParamsResolvers[static::class] = $closure;
    }

    protected static function getFetchParamsResolver(): ?\Closure
    {
        return static::$fetchParamsResolvers[static::class] ?? null;
    }

    /**
     * Delete the model via API.
     */
    public function delete(): ?bool
    {
        try {
            $key = $this->getKey();
            if (empty($key)) {
                $this->handleError(HttpModelException::missingId());
                return false;
            }

            $this->httpClient()->delete('/' . $key)->throw();
            return true;
        } catch (\Exception $exception) {
            $this->handleError(HttpModelException::apiRequestFailed('DELETE', '/' . ($this->getKey() ?? ''), $exception));
            return null;
        }
    }

    /**
     * Create a new model instance via API.
     */
    public static function create(array $attributes = []): ?self
    {
        try {
            $instance = new static();
            $response = $instance->httpClient()->post('/', $attributes)->throw()->json();

            if (!is_array($response)) {
                $instance->handleError(HttpModelException::invalidResponseFormat('array', $response));
                return null;
            }

            return static::hydrateModelFromResponse($response);
        } catch (\Exception $exception) {
            $instance = new static();
            $instance->handleError(HttpModelException::apiRequestFailed('POST', '/', $exception));
            return null;
        }
    }

    /**
     * Update the model via API.
     */
    public function update(array $attributes = [], array $options = []): ?self
    {
        try {
            $key = $this->getKey();
            if (empty($key)) {
                $this->handleError(HttpModelException::missingId());
                return null;
            }

            $response = $this->httpClient()->put('/' . $key, $attributes)->throw()->json();

            if (!is_array($response)) {
                $this->handleError(HttpModelException::invalidResponseFormat('array', $response));
                return null;
            }

            return static::hydrateModelFromResponse($response);
        } catch (\Exception $exception) {
            $this->handleError(HttpModelException::apiRequestFailed('PUT', '/' . ($this->getKey() ?? ''), $exception));
            return null;
        }
    }

    /**
     * Save the model via API (POST when not exists, PUT otherwise).
     */
    public function save(array $options = []): ?self
    {
        try {
            $key = $this->getKey();
            $payload = $this->attributesToArray();

            if (!$this->exists || empty($key)) {
                // Create
                $response = $this->httpClient()->post('/', $payload)->throw()->json();
            } else {
                // Update
                $response = $this->httpClient()->put('/' . $key, $payload)->throw()->json();
            }

            if (!is_array($response)) {
                $this->handleError(HttpModelException::invalidResponseFormat('array', $response));
                return null;
            }

            $model = static::hydrateModelFromResponse($response);
            if ($model) {
                $model->exists = true;
            }
            return $model;
        } catch (\Exception $exception) {
            $method = (!$this->exists || empty($this->getKey())) ? 'POST' : 'PUT';
            $url = (!$this->exists || empty($this->getKey())) ? '/' : ('/' . $this->getKey());
            $this->handleError(HttpModelException::apiRequestFailed($method, $url, $exception));
            return null;
        }
    }

    /**
     * Find a model by its primary key.
     */
    public static function find($id, $columns = ['*'])
    {
        try {
            $instance = new static();
            $response = $instance->httpClient()->get('/' . $id)->throw()->json();

            if (!is_array($response)) {
                $instance->handleError(HttpModelException::invalidResponseFormat('array', $response));
                return null;
            }

            return static::hydrateModelFromResponse($response);
        } catch (\Exception $exception) {
            $instance = new static();
            $instance->handleError(HttpModelException::apiRequestFailed('GET', '/' . $id, $exception));
            return null;
        }
    }

    /**
     * Find a model by its primary key or throw an exception.
     */
    public static function findOrFail($id, $columns = ['*'])
    {
        $model = static::find($id, $columns);

        if (!$model) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(static::class, $id);
        }

        return $model;
    }

    public function handleError(HttpModelException $exception): void
    {
        if (config('eloquent-http-adapter.error_handling.log_errors', true)) {
            report($exception);
        }

        if (config('eloquent-http-adapter.error_handling.throw_exceptions', false)) {
            throw $exception;
        }
    }

    public function getApiEndpoint(): ?string
    {
        return $this->apiEndpoint;
    }

    public function setApiEndpoint(string $endpoint): void
    {
        $this->apiEndpoint = $endpoint;
    }

    /**
     * Prefer HTTP include loading for missing relations instead of DB lazy-load.
     */
    public function getRelationValue($key)
    {
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            $this->load($key);
            if ($this->relationLoaded($key)) {
                return $this->relations[$key];
            }
        }

        return parent::getRelationValue($key);
    }

    /**
     * Optional map of short column names to relation-aware filter keys for client-side filtering.
     * Example: ['name' => 'user.name', 'email' => 'user.email']
     */
    public function getFilterAliases(): array
    {
        return [];
    }

    /**
     * Load relations over HTTP by re-fetching with include parameter.
     *
     * @param array|string $relations
     * @return $this
     */
    public function load($relations)
    {
        $relations = is_array($relations) ? array_values($relations) : func_get_args();

        $key = $this->getKey();
        if (empty($key)) {
            return $this;
        }

        $includeKey = config('eloquent-http-adapter.query_builder.include_prefix', 'include');

        try {
            $response = $this->httpClient()->get('/' . $key, [
                $includeKey => implode(',', $relations),
            ])->throw()->json();

            if (is_array($response)) {
                $this->initializeIncludedRelationsFromAttributes($response);
            }
        } catch (\Exception $exception) {
            $this->handleError(HttpModelException::apiRequestFailed('GET', '/' . $key, $exception));
        }

        return $this;
    }

    /**
     * Load missing relations over HTTP.
     *
     * @param array|string $relations
     * @return $this
     */
    public function loadMissing($relations)
    {
        $relations = is_array($relations) ? array_values($relations) : func_get_args();
        $toLoad = array_values(array_filter($relations, fn($name) => !$this->relationLoaded($name)));
        if (!empty($toLoad)) {
            $this->load($toLoad);
        }
        return $this;
    }

    /**
     * Convert included arrays from API response to loaded relations using relationClassMap.
     */
    public function initializeIncludedRelations(): void
    {
        $this->initializeIncludedRelationsFromAttributes($this->getAttributes());
    }

    protected static function hydrateModelFromResponse(array $response): ?self
    {
        /** @var self|null $model */
        $model = static::query()->hydrate([$response])->first();
        if ($model instanceof self) {
            $model->initializeIncludedRelationsFromAttributes($response);
        }
        return $model;
    }

    protected function initializeIncludedRelationsFromAttributes(array $attributes): void
    {
        $map = $this->relationClassMap;

        // 1) Try to derive from real Eloquent relation methods (reflection)
        if (empty($map)) {
            $map = $this->discoverRelationClassMapByReflection();
        }

        // 2) Fallback: guess by attribute keys and conventional names
        if (empty($map)) {
            $map = $this->guessRelationClassMapFromAttributes($attributes);
        }

        if (empty($map)) {
            return;
        }

        foreach ($map as $relationName => $relatedClass) {
            if (!array_key_exists($relationName, $attributes)) {
                continue;
            }

            $value = $attributes[$relationName];

            if (is_array($value) && $this->isListArray($value)) {
                $relatedModels = [];
                foreach ($value as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $related = new $relatedClass();
                    $related->forceFill($item);
                    $related->exists = true;
                    $relatedModels[] = $related;
                }
                $this->setRelation($relationName, new EloquentCollection($relatedModels));
            } elseif (is_array($value)) {
                $related = new $relatedClass();
                $related->setAttribute($related->getKeyName(), $value[$related->getKeyName()] ?? null);
                $related->forceFill($value);
                $related->exists = true;
                $this->setRelation($relationName, $related);
            }
        }
    }

    private function isListArray(array $array): bool
    {
        // PHP 8.0 compatibility for array_is_list
        $expectedKey = 0;
        foreach ($array as $key => $_) {
            if ($key !== $expectedKey) {
                return false;
            }
            $expectedKey++;
        }
        return true;
    }

    /**
     * Guess relation class map based on attribute keys and conventional model names under App\\Models.
     */
    protected function guessRelationClassMapFromAttributes(array $attributes): array
    {
        $map = [];
        foreach ($attributes as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            $guessed = $this->guessModelClassFromKey($key);
            if ($guessed && is_subclass_of($guessed, Model::class)) {
                $map[$key] = $guessed;
            }
        }
        return $map;
    }

    protected function guessModelClassFromKey(string $key): ?string
    {
        $studly = Str::studly($key);
        $candidates = [
            $studly,
            Str::of($studly)->singular()->toString(),
        ];
        foreach ($candidates as $name) {
            $fqcn = "App\\Models\\{$name}";
            if (class_exists($fqcn)) {
                return $fqcn;
            }
        }
        return null;
    }

    /**
     * Discover relation class map using reflection of Eloquent relation methods.
     */
    protected function discoverRelationClassMapByReflection(): array
    {
        $map = [];
        $class = new ReflectionClass($this);
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfParameters() !== 0 || $method->isStatic() || $method->class !== $class->getName()) {
                continue;
            }
            $relatedClass = null;
            $rt = $method->getReturnType();
            if ($rt && class_exists($rt->getName()) && is_subclass_of($rt->getName(), Relation::class)) {
                try {
                    /** @var Relation $rel */
                    $rel = Relation::noConstraints(fn() => $this->{$method->getName()}());
                    $relatedClass = get_class($rel->getRelated());
                } catch (\Throwable) {
                }
            }
            if ($relatedClass === null) {
                try {
                    $rel = Relation::noConstraints(fn() => $this->{$method->getName()}());
                    if ($rel instanceof Relation) {
                        $relatedClass = get_class($rel->getRelated());
                    }
                } catch (\Throwable) {
                }
            }
            if ($relatedClass) {
                $map[$method->getName()] = $relatedClass;
            }
        }
        return $map;
    }
}
