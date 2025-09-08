<?php

namespace Iafilin\EloquentHttpAdapter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class HttpQueryBuilder extends Builder
{
    private int $paginatePerPage = 15;
    private int $paginatePage = 1;
    private ?Response $response = null;
    private bool $dataFetched = false;
    private bool $isPaginated = false;
    private PendingRequest $httpClient;
    private ?\Closure $fetchParamsResolver;

    public function __construct(
        QueryBuilder $query,
        PendingRequest $httpClient,
        ?\Closure $fetchParamsResolver = null
    ) {

        parent::__construct($query);
        $this->httpClient = $httpClient;
        $this->fetchParamsResolver = $fetchParamsResolver;

        $this->paginatePerPage = (int) config('eloquent-http-adapter.pagination.default_per_page', 15);
    }

    protected function fetchDataIfNeeded(): void
    {
        if (!$this->dataFetched) {
            $this->fetchData();
            $this->dataFetched = true;
        }
    }

    protected function runSelect(): ?array
    {
        $this->fetchDataIfNeeded();

        if (!$this->response || !$this->response->successful()) {
            return null;
        }

        $dataKey = config('eloquent-http-adapter.response.data_key', 'data');
        $data = $this->response->json($dataKey);
        return is_array($data) ? $data : null;
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = null, $page = null, $total = null): LengthAwarePaginator
    {
        $pageName = $pageName ?: config('eloquent-http-adapter.pagination.page_name', 'page');

        $this->paginatePerPage = $perPage ?: $this->paginatePerPage;
        $this->paginatePage = $page ?: Paginator::resolveCurrentPage($pageName);
        $this->isPaginated = true;

        // Always refetch to avoid stale data after mutations (e.g., bulk delete)
        $this->dataFetched = false;
        $this->fetchData();
        $this->dataFetched = true;

        if (!$this->response || !$this->response->successful()) {
            return new LengthAwarePaginator([], 0, $this->paginatePerPage, $this->paginatePage);
        }

        $dataKey = config('eloquent-http-adapter.response.data_key', 'data');
        $totalKey = config('eloquent-http-adapter.response.total_key', 'total');
        $perPageKey = config('eloquent-http-adapter.response.per_page_key', 'per_page');

        $data = $this->response->json($dataKey) ?? [];
        $total = $this->response->json($totalKey) ?? 0;
        $perPage = $this->response->json($perPageKey) ?? $this->paginatePerPage;

        $collection = $this->hydrate($data);
        $this->initializeIncludedRelationsForCollection($collection);

        if (!$this->response || !$this->response->successful()) {
            return new LengthAwarePaginator([], 0, $this->paginatePerPage, $this->paginatePage);
        }

        $dataKey = config('eloquent-http-adapter.response.data_key', 'data');
        $totalKey = config('eloquent-http-adapter.response.total_key', 'total');
        $perPageKey = config('eloquent-http-adapter.response.per_page_key', 'per_page');

        $data = $this->response->json($dataKey) ?? [];
        $total = $this->response->json($totalKey) ?? 0;
        $perPage = $this->response->json($perPageKey) ?? $this->paginatePerPage;

        $collection = $this->hydrate($data);
        $this->initializeIncludedRelationsForCollection($collection);

        return new LengthAwarePaginator(
            $collection,
            $total,
            $perPage,
            $this->paginatePage
        );
    }

    public function count($columns = '*'): int
    {
        // Always refetch to avoid stale data after mutations
        $this->dataFetched = false;
        $this->fetchData();
        $this->dataFetched = true;


        if (!$this->response || !$this->response->successful()) {
            return 0;
        }

        $dataKey = config('eloquent-http-adapter.response.data_key', 'data');
        $data = $this->response->json($dataKey) ?? [];
        return parent::hydrate($data)->count();
    }

    public function get($columns = ['*'])
    {
        $this->paginatePage = 1;
        $this->paginatePerPage = $this->getMaxPerPage();
        $this->isPaginated = false;
        // Always refetch to avoid stale data after mutations
        $this->dataFetched = false;
        $this->fetchData();
        $this->dataFetched = true;

        if (!$this->response || !$this->response->successful()) {
            return $this->getModel() ? $this->getModel()->newCollection() : new \Illuminate\Database\Eloquent\Collection();
        }


        $dataKey = config('eloquent-http-adapter.response.data_key', 'data');
        $data = $this->response->json($dataKey) ?? [];
        $collection = $this->hydrate($data);
        $this->initializeIncludedRelationsForCollection($collection);
        return $collection;
    }

    protected function getMaxPerPage(): int
    {
        return (int) config('eloquent-http-adapter.pagination.max_per_page', 1000);
    }

    private function fetchData(): void
    {
        $params = $this->httpQueryParams();
        $cacheEnabled = (bool) config('eloquent-http-adapter.cache.enabled', false);

        if ($cacheEnabled) {
            $ttl = (int) config('eloquent-http-adapter.cache.ttl', 300);
            $cacheKey = $this->buildCacheKey($params);
            $this->response = Cache::remember($cacheKey, $ttl, function () use ($params) {
                return $this->httpClient->get('/', $params);
            });
        } else {
            $this->response = $this->httpClient->get('/', $params);
        }
    }

    private function buildCacheKey(Collection $params): string
    {
        $model = $this->getModel() ? get_class($this->getModel()) : 'no-model';
        $base = implode('|', [
            $model,
            (string) $this->paginatePage,
            (string) $this->paginatePerPage,
            json_encode($params->toArray()),
            $this->isPaginated ? 'paginated' : 'all',
        ]);
        return 'httpqb:' . sha1($base);
    }


    private function httpQueryParams(): Collection
    {
        if ($this->fetchParamsResolver instanceof \Closure) {
            try {
                $fetchParams = $this->fetchParamsResolver->call($this, $this->paginatePage, $this->paginatePerPage);

                if ($fetchParams instanceof Collection) {
                    return $fetchParams;
                }

                if (is_array($fetchParams)) {
                    return collect($fetchParams);
                }

                throw new Exception('fetchParamsResolver must return Collection or array');
            } catch (Exception $e) {
                report($e);
                return collect();
            }
        }

        $pageName = config('eloquent-http-adapter.pagination.page_name', 'page');
        $perPageName = config('eloquent-http-adapter.pagination.per_page_name', 'per_page');

        $params = collect([
            $pageName => $this->paginatePage,
            $perPageName => $this->paginatePerPage
        ]);

        $this->parseWheres($params, $this->getQuery()->wheres);

        if ($this->eagerLoad) {
            $includeKey = config('eloquent-http-adapter.query_builder.include_prefix', 'include');
            $params->put($includeKey, implode(',', array_keys($this->eagerLoad)));
        }

        if ($this->getQuery()->orders) {
            $sortPrefix = config('eloquent-http-adapter.query_builder.sort_prefix', 'sort');
            $sortParams = collect($this->getQuery()->orders)
                ->map(fn($order) => $order['direction'] === 'asc' ? $order['column'] : "-{$order['column']}")
                ->implode(',');
            $params->put($sortPrefix, $sortParams);
        }

        return $params;
    }

    private function parseWheres(Collection $params, array $wheres): Collection
    {
        $processedWheres = [];

        foreach ($wheres as $where) {
            if (!is_array($where)) {
                // Unsupported where shape; skip safely
                continue;
            }
            // Support Eloquent's "where key in (...)" used by Filament selection
            if (isset($where['type']) && strtolower((string) $where['type']) === 'in') {
                if (isset($where['column'])) {
                    $column = $this->normalizeFilterKey($where['column']);
                    $values = $where['values'] ?? [];
                    $this->addWhereParameter($params, $column, 'in', $values);
                }
                continue;
            }

            // Some where clauses may contain Closures or non-serializable objects.
            // Normalize them to a JSON-serializable structure before hashing.
            $whereHash = md5(json_encode($this->normalizeWhereForHash($where)));
            if (in_array($whereHash, $processedWheres)) {
                continue;
            }
            $processedWheres[] = $whereHash;


            // Unwrap nested groups (e.g., global search OR group)
            if (isset($where['type']) && $where['type'] === 'Nested') {
                if (isset($where['query']) && $where['query'] instanceof QueryBuilder) {
                    $params = $params->merge($this->parseWheres($params, $where['query']->wheres));
                }
                continue;
            }

            if (isset($where['column'])) {
                $column = $this->normalizeFilterKey($where['column']);
                $operator = $where['operator'] ?? '=';
                $value = $where['value'] ?? $where['values'] ?? [];

                $this->addWhereParameter($params, $column, $operator, $value);
            }
            // Some OR-groups may have no column (e.g., raw exists). We ignore those client-side.
        }

        return $params;
    }

    private function normalizeFilterKey($column): string
    {
        $columnStr = (string) $column;
        // Apply model-provided filter aliases
        $aliases = [];
        if ($this->getModel() && method_exists($this->getModel(), 'getFilterAliases')) {
            $aliases = (array) $this->getModel()->getFilterAliases();
            if (isset($aliases[$columnStr])) {
                $columnStr = (string) $aliases[$columnStr];
            }
        }
        // Normalize table-qualified columns to relation paths for the server side
        // Example: users.name -> user.name (keep goods/specifications as-is)
        if (str_contains($columnStr, '.')) {
            [$first, $rest] = explode('.', $columnStr, 2);
            $first = $this->mapTableToRelation($first);
            return trim($first . '.' . $rest, '.');
        }
        return $columnStr;
    }

    private function mapTableToRelation(string $table): string
    {
        $table = strtolower($table);
        // Special-case common relation names that differ from table
        if ($table === 'users') {
            return 'user';
        }
        // Leave "goods" as-is (relation is goods, not good)
        if ($table === 'goods') {
            return 'goods';
        }
        // Default: no change
        return $table;
    }

    private function addWhereParameter(Collection $params, string $column, string $operator, $value): void
    {
        $filterPrefix = config('eloquent-http-adapter.query_builder.filter_prefix', 'filter');
        $key = "{$filterPrefix}[{$column}]";


        switch (strtolower($operator)) {
            case '=':
                $params->put($key, is_array($value) ? implode(',', $value) : $value);
                break;

            case 'in':
                if (is_array($value)) {
                    $params->put($key, implode(',', $value));
                }
                break;

            case '!=':
                $params->put($key, "!{$value}");
                break;

            case '>':
            case '<':
            case '>=':
            case '<=':
                $params->put($key, "{$operator}{$value}");
                break;

            case 'like':
                $cleanValue = str_replace(['%', '_'], ['*', '?'], (string) $value);
                $params->put($key, $cleanValue);
                break;

            case 'between':
                if (is_array($value) && count($value) === 2) {
                    $params->put($key, "{$value[0]},{$value[1]}");
                }
                break;

            default:
                break;
        }
    }

    private function initializeIncludedRelationsForCollection($collection): void
    {
        foreach ($collection as $model) {
            if (method_exists($model, 'initializeIncludedRelations')) {
                $model->initializeIncludedRelations();
            }
        }
    }

    /**
     * Normalize a where clause structure into a JSON-serializable form for hashing/deduping.
     * - Closures are replaced with a string marker
     * - QueryBuilder instances are represented by their nested where structures
     * - DateTime-like objects are formatted
     * - Other objects are replaced by their class name
     */
    private function normalizeWhereForHash($value)
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $k => $v) {
                $normalized[$k] = $this->normalizeWhereForHash($v);
            }
            return $normalized;
        }

        if ($value instanceof \Closure) {
            return 'closure';
        }

        // Treat Stringable or objects with __toString as their string value to avoid dedupe collisions
        if ($value instanceof \Stringable || (is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        if ($value instanceof QueryBuilder) {
            // Represent builder by its where conditions only to keep it deterministic
            return [
                'query' => 'builder',
                'wheres' => $this->normalizeWhereForHash($value->wheres ?? []),
            ];
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_object($value)) {
            // As a safe fallback, use class name so hashing is stable
            return '\\object:' . get_class($value);
        }

        return $value;
    }
}
