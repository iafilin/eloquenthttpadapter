<?php

namespace Iafilin\EloquentHttpAdapter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class HttpQueryBuilder extends Builder
{
    private int $paginatePerPage = 0;

    private int $paginatePage = 1;

    private ?Response $response = null;

    public function __construct(QueryBuilder $query, private readonly PendingRequest $httpClient)
    {
        parent::__construct($query);

    }

    protected function runSelect()
    {
        $this->fetchData();

        return $this->response->json(['data']);
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $this->paginatePerPage = $perPage;
        $this->paginatePage = $page;

        $this->fetchData();

        return new LengthAwarePaginator($this->hydrate($this->response->json('data')), $this->response->json('total'), $this->response->json('per_page'));
    }

    public function count($columns = '*')
    {
        $this->fetchData();

        return parent::hydrate($this->response->json('data'))->count();
    }

    public function delete(): ?bool
    {
        try {
            \Http::backend()->delete($this->id)->throw();

            return true;
        } catch (\Exception $exception) {
            report($exception);

            return null;
        }
    }

    public function get($columns = ['*'])
    {
        $this->paginatePage = 1;
        $this->paginatePerPage = 1000;
        $this->fetchData();

        return $this->hydrate($this->response->json('data'));
    }

    private function fetchData(): void
    {
        $params = collect();

        $params->put('page', $this->paginatePage);
        $params->put('per_page', $this->paginatePerPage);

        foreach ($this->getQuery()->wheres as $where) {
            $column = str($where['column'])->explode('.')->last();
            if (isset($where['value'])) {
                $params->put("filter[{$column}]", $where['value']);
            }

            if (isset($where['values'])) {
                $params->put("filter[{$column}]", implode(',', $where['values']));
            }
        }

        if (count($this->eagerLoad) > 0) {
            $params->put('include', implode(',', array_keys($this->eagerLoad)));
        }

        if ($this->getQuery()->orders) {
            $params->put('sort', collect($this->getQuery()->orders)->map(function ($order) {
                return match ($order['direction']) {
                    'asc' => $order['column'],
                    'desc' => "-{$order['column']}"
                };
            })->implode(','));
        }

        $this->response = $this->httpClient->get('/', $params->toArray());
    }
}
