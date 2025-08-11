<?php

namespace Iafilin\EloquentHttpAdapter\Server;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

class QueryApplier
{
    public function apply(Request $request, EloquentBuilder $eloquentBuilder): EloquentBuilder
    {
        $this->applyIncludes($request, $eloquentBuilder);
        $this->applyFilters($request, $eloquentBuilder->getQuery());
        $this->applySorts($request, $eloquentBuilder->getQuery());
        return $eloquentBuilder;
    }

    public function applyPagination(Request $request, EloquentBuilder $eloquentBuilder): EloquentBuilder
    {
        // No-op here; pagination is handled at response time using paginator.
        return $eloquentBuilder;
    }

    protected function applyIncludes(Request $request, EloquentBuilder $builder): void
    {
        $includeKey = config('eloquent-http-adapter.query_builder.include_prefix', 'include');

        $rawIncludes = (string) $request->query($includeKey, '');
        if ($rawIncludes === '') {
            return;
        }

        $requestedIncludes = collect(explode(',', $rawIncludes))
            ->map(fn(string $name) => trim($name))
            ->filter(fn(string $name) => $name !== '')
            ->values();

        $allowedIncludes = collect((array) config('eloquent-http-adapter.server.allowed_includes', []));
        if ($allowedIncludes->isNotEmpty()) {
            $requestedIncludes = $requestedIncludes->intersect($allowedIncludes);
        }

        if ($requestedIncludes->isEmpty()) {
            return;
        }

        $builder->with($requestedIncludes->all());
    }

    protected function applySorts(Request $request, QueryBuilder $query): void
    {
        $sortKey = config('eloquent-http-adapter.query_builder.sort_prefix', 'sort');
        $rawSort = (string) $request->query($sortKey, '');
        if ($rawSort === '') {
            return;
        }

        $allowedSorts = collect((array) config('eloquent-http-adapter.server.allowed_sorts', []));

        foreach (explode(',', $rawSort) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $direction = 'asc';
            $column = $segment;
            if (str_starts_with($segment, '-')) {
                $direction = 'desc';
                $column = substr($segment, 1);
            }

            if ($allowedSorts->isNotEmpty() && !$allowedSorts->contains($column)) {
                continue;
            }

            $query->orders = $query->orders ?? [];
            $query->orders[] = [
                'column' => $column,
                'direction' => $direction,
            ];
        }
    }

    protected function applyFilters(Request $request, QueryBuilder $query): void
    {
        $filterPrefix = config('eloquent-http-adapter.query_builder.filter_prefix', 'filter');
        $filters = (array) $request->query($filterPrefix, []);
        if (empty($filters)) {
            return;
        }

        $allowedFilters = collect((array) config('eloquent-http-adapter.server.allowed_filters', []));
        $betweenColumns = collect((array) config('eloquent-http-adapter.server.between_columns', []));
        $inferBetween = (bool) config('eloquent-http-adapter.server.infer_between', true);

        foreach ($filters as $column => $rawValue) {
            if (!is_string($column) || $column === '') {
                continue;
            }

            if ($allowedFilters->isNotEmpty() && !$allowedFilters->contains($column)) {
                continue;
            }

            if (is_array($rawValue)) {
                // Interpret array as IN list
                $this->applyIn($query, $column, $rawValue);
                continue;
            }

            $value = is_string($rawValue) ? trim($rawValue) : $rawValue;
            if ($value === '' || $value === null) {
                continue;
            }

            // Operators by prefix
            foreach (['>=', '<=', '>', '<'] as $op) {
                if (is_string($value) && str_starts_with($value, $op)) {
                    $query->wheres[] = [
                        'type' => 'Basic',
                        'column' => $column,
                        'operator' => $op,
                        'value' => ltrim((string) $value, $op),
                        'boolean' => 'and',
                    ];
                    continue 2;
                }
            }

            // Not equal via leading '!'
            if (is_string($value) && str_starts_with($value, '!')) {
                $query->wheres[] = [
                    'type' => 'Basic',
                    'column' => $column,
                    'operator' => '!=',
                    'value' => substr((string) $value, 1),
                    'boolean' => 'and',
                ];
                continue;
            }

            // LIKE: presence of wildcard symbols from client-side mapping ('*' => '%', '?' => '_')
            if (is_string($value) && (str_contains($value, '*') || str_contains($value, '?'))) {
                $like = str_replace(['*', '?'], ['%', '_'], $value);
                $query->wheres[] = [
                    'type' => 'Basic',
                    'column' => $column,
                    'operator' => 'like',
                    'value' => $like,
                    'boolean' => 'and',
                ];
                continue;
            }

            // Comma => IN or BETWEEN
            if (is_string($value) && str_contains($value, ',')) {
                $parts = array_values(array_filter(array_map('trim', explode(',', $value)), fn($v) => $v !== ''));
                if ($inferBetween && count($parts) === 2 && ($betweenColumns->isEmpty() || $betweenColumns->contains($column))) {
                    $query->wheres[] = [
                        'type' => 'Between',
                        'column' => $column,
                        'operator' => 'between',
                        'values' => [$parts[0], $parts[1]],
                        'boolean' => 'and',
                    ];
                    continue;
                }

                if (!empty($parts)) {
                    $this->applyIn($query, $column, $parts);
                    continue;
                }
            }

            // Fallback '=' equality
            $query->wheres[] = [
                'type' => 'Basic',
                'column' => $column,
                'operator' => '=',
                'value' => $value,
                'boolean' => 'and',
            ];
        }
    }

    protected function applyIn(QueryBuilder $query, string $column, array $values): void
    {
        $query->wheres[] = [
            'type' => 'In',
            'column' => $column,
            'operator' => 'in',
            'values' => array_values($values),
            'boolean' => 'and',
        ];
    }
}


