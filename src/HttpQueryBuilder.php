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

class HttpQueryBuilder extends Builder
{
    private int $paginatePerPage = 15;
    private int $paginatePage = 1;
    private ?Response $response = null;
    private bool $dataFetched = false;


    public function __construct(
        QueryBuilder                    $query,
        private readonly PendingRequest $httpClient,
        private readonly ?\Closure      $fetchParamsResolver = null
    )
    {
        parent::__construct($query);
    }

    /**
     * Lazy-load data if not yet fetched.
     */
    protected function fetchDataIfNeeded(): void
    {
        if (!$this->dataFetched) {
            $this->fetchData();
            $this->dataFetched = true;
        }
    }

    /**
     * Override the runSelect method to retrieve data via HTTP.
     *
     * @return array|null
     */
    protected function runSelect(): ?array
    {
        $this->fetchDataIfNeeded();
        return $this->response->json('data');
    }

    /**
     * Paginate results via the API.
     *
     * @param int|null $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return LengthAwarePaginator
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null): LengthAwarePaginator
    {
        $this->paginatePerPage = $perPage ?: $this->paginatePerPage;
        $this->paginatePage = $page ?: Paginator::resolveCurrentPage($pageName);

        $this->fetchDataIfNeeded();

        return new LengthAwarePaginator(
            $this->hydrate($this->response->json('data')),
            $this->response->json('total'),
            $this->response->json('per_page'),
            $this->paginatePage
        );
    }

    /**
     * Retrieve the count of records via API.
     *
     * @param string $columns
     * @return int
     */
    public function count($columns = '*'): int
    {
        $this->fetchDataIfNeeded();
        return parent::hydrate($this->response->json('data'))->count();
    }


    /**
     * Retrieve all records via the API.
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($columns = ['*'])
    {
        $this->paginatePage = 1;
        $this->paginatePerPage = 1000;
        $this->fetchDataIfNeeded();

        return $this->hydrate($this->response->json('data'));
    }

    /**
     * Fetch data from the server
     *
     * @return void
     * @throws Exception
     */
    private function fetchData(): void
    {
        // Execute HTTP request with parameters formatted for Spatie QueryBuilder API on the server side
        $this->response = $this->httpClient->get('/', $this->httpQueryParams());
    }

    /**
     * Get HTTP query parameters
     * @return Collection
     * @throws Exception
     */
    private function httpQueryParams(): \Illuminate\Support\Collection
    {
        if ($this->fetchParamsResolver instanceof \Closure) {
            $fetchParams = $this->fetchParamsResolver->call($this);


            if ($fetchParams instanceof Collection) {
                return $fetchParams;
            }

            if (is_array($fetchParams)) {
                return collect($fetchParams);
            }

            throw new Exception('fetchParamsResolver must return Collection or array');
        }
        $params = collect([
            'page' => $this->paginatePage,
            'per_page' => $this->paginatePerPage
        ]);

        $this->parseWheres($params, $this->getQuery()->wheres);


        // Обработка eager load (включение связанных данных)
        if ($this->eagerLoad) {
            $params->put('include', implode(',', array_keys($this->eagerLoad)));
        }

        // Обработка сортировки
        if ($this->getQuery()->orders) {
            $sortParams = collect($this->getQuery()->orders)
                ->map(fn($order) => $order['direction'] === 'asc' ? $order['column'] : "-{$order['column']}")
                ->implode(',');
            $params->put('sort', $sortParams);
        }

        return $params;
    }

    /**
     * Parse where clauses and add them to the request parameters
     * @param Collection $params
     * @param array $wheres
     * @return Collection
     */
    private function parseWheres(\Illuminate\Support\Collection $params, array $wheres): Collection
    {
        foreach ($wheres as $where) {
            if (isset($where['type']) && $where['type'] === 'Nested') {
                $params = $params->merge($this->parseWheres($params, $where['query']->wheres));
            }

            if (isset($where['column'])) {
                $column = last(explode('.', $where['column']));
                $operator = $where['operator'] ?? '=';
                $value = $where['value'];

                switch (strtolower($operator)) {
                    case '=':
                        $params->put("filter[{$column}]", $value);
                        break;
                    case 'in':
                        if (is_array($value)) {
                            $params->put("filter[{$column}]", implode(',', $value));
                        }
                        break;
                    case '!=':
                        // Эмулируем NOT EQUAL через специальный формат, например, `!value`
                        $params->put("filter[{$column}]", "!{$value}");
                        break;
                    case '>':
                    case '<':
                    case '>=':
                    case '<=':
                        $params->put("filter[{$column}]", "{$operator}{$value}");
                        break;
                    case 'like':
                        // Преобразование LIKE в поддерживаемый формат, например, заменяя `%` на `*`
                        $params->put("filter[{$column}]", str($value)->replace('%', '')->toString());
                        break;
                    case 'between':
                        if (is_array($value) && count($value) === 2) {
                            $params->put("filter[{$column}]", "{$value[0]},{$value[1]}");
                        }
                        break;
                    default:
                        // Дополнительные операторы не поддерживаются laravel-query-builder
                        break;
                }
            }
        }
        return $params;
    }

}
