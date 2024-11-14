<?php

namespace Iafilin\EloquentHttpAdapter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Exception;

class HttpQueryBuilder extends Builder
{
    private int $paginatePerPage = 15;
    private int $paginatePage = 1;
    private ?Response $response = null;
    private bool $dataFetched = false;

    public function __construct(QueryBuilder $query, private readonly PendingRequest $httpClient)
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
    protected function runSelect()
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
     * Delete a record via the API.
     *
     * @return bool|null
     */
    public function delete(): ?bool
    {
        try {
            $this->httpClient->delete('/' . $this->getModel()->getKey())->throw();
            return true;
        } catch (Exception $exception) {
            report($exception);
            return null;
        }
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
     * Core method to build API parameters and perform the request.
     *
     * @return void
     */
    private function fetchData(): void
    {
        $params = collect([
            'page' => $this->paginatePage,
            'per_page' => $this->paginatePerPage
        ]);

        // Convert filters into API query parameters
        foreach ($this->getQuery()->wheres as $where) {

            $column = last(explode('.', $where['column']));
            $operator = $where['operator'] ?? '=';
            $value = $where['value'];

            if ($operator === '=') {
                $params->put("filter[{$column}]", $value);
            } elseif ($operator === 'in' && isset($where['values'])) {
                $params->put("filter[{$column}]", implode(',', $where['values']));
            } elseif (in_array($operator, ['>', '<', '>=', '<=', '!='])) {
                // Format operator and value for Spatie QueryBuilder API support
                $params->put("filter[{$column}]", "{$operator}{$value}");
            }
        }

        // Convert eager loading relations to API include parameters
        if ($this->eagerLoad) {
            $params->put('include', implode(',', array_keys($this->eagerLoad)));
        }

        // Convert sorting to API sort parameters
        if ($this->getQuery()->orders) {
            $sortParams = collect($this->getQuery()->orders)
                ->map(fn($order) => $order['direction'] === 'asc' ? $order['column'] : "-{$order['column']}")
                ->implode(',');
            $params->put('sort', $sortParams);
        }

        // Execute HTTP request with parameters formatted for Spatie QueryBuilder API on the server side
        $this->response = $this->httpClient->get('/', $params->toArray());
    }
}
