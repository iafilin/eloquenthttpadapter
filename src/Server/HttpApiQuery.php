<?php
<?php

namespace Iafilin\EloquentHttpAdapter\Server;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;


class HttpApiQuery
{
    /**
     * Apply common admin API conventions (filters, sorts, includes, pagination) to a query.
     *
     * Supported request params (aligned with frontend HTTP adapter):
     * - page, per_page
     * - include=rel1,rel2 (only from $allowedIncludes)
     * - sort=col1,-col2 (only from $allowedSorts)
     * - filter[field]=value with operators:
     *      =  equal (default)
     *      in values (comma-separated): 1,2,3
     *      != not equal: !value
     *      >, <, >=, <= comparisons: e.g. ">2024-01-01"
     *      like wildcards: * and ? will be converted to SQL %% and _
     *      between for date-like fields: "start,end" (only if $field is in $betweenFields)
     */
    public static function paginate(
        EloquentBuilder|QueryBuilder $baseQuery,
        Request $request,
        array $allowedFilters = [],
        array $allowedSorts = [],
        array $allowedIncludes = [],
        array $betweenFields = [],
        array $fuzzyLikeFields = [],
        array $searchableFields = [],
        ?int $defaultPerPage = null,
        array $customFilterCallbacks = [], // [field => function($q, $method, $operator, $value)]
        array $fieldAliases = [], // e.g. ['name' => 'user.name']
        array $compositeOrFilters = [] // e.g. ['goods_summary' => ['goods.title','goods.specifications.size']]
    ): LengthAwarePaginator {
        // Preserve previous defaults; allow override per controller
        $fallbackPerPage = $defaultPerPage ?? (int) config('eloquent-http-adapter.pagination.default_per_page', 100);
        $perPage = (int) ($request->get('per_page', $fallbackPerPage));

        // Includes
        $includes = array_filter(explode(',', (string) $request->get('include', '')));
        if (!empty($allowedIncludes) && !empty($includes) && method_exists($baseQuery, 'with')) {
            $baseQuery->with(array_values(array_intersect($includes, $allowedIncludes)));
        }

        // Filters
        $filters = (array) $request->get('filter', []);

        // Expand composite OR filters into grouped OR conditions and remove them from $filters
        foreach ($compositeOrFilters as $virtualField => $targetFields) {
            if (!array_key_exists($virtualField, $filters)) {
                continue;
            }
            $raw = $filters[$virtualField];
            unset($filters[$virtualField]);
            [$operator, $value] = self::parseFilterValue($raw);
            $baseQuery->where(function ($q) use ($targetFields, $operator, $value, $customFilterCallbacks, $fieldAliases, $fuzzyLikeFields) {
                foreach ($targetFields as $idx => $field) {
                    $method = $idx === 0 ? 'where' : 'orWhere';
                    $alias = $fieldAliases[$field] ?? $field;
                    if (isset($customFilterCallbacks[$alias]) && is_callable($customFilterCallbacks[$alias])) {
                        ($customFilterCallbacks[$alias])($q, $method, $operator, $value);
                        continue;
                    }
                    self::applyFieldWhere($q, $method, $alias, $operator, $value, $fuzzyLikeFields);
                }
            });
        }

        // Global search pattern: same filter value applied to multiple fields → OR across these fields
        if (!empty($filters)) {
            $valueToFields = [];
            foreach ($filters as $field => $raw) {
                $alias = $fieldAliases[$field] ?? $field;
                if (
                    !empty($allowedFilters)
                    && !in_array($field, $allowedFilters, true)
                    && !in_array($alias, $allowedFilters, true)
                ) {
                    continue;
                }
                $key = is_array($raw) ? json_encode($raw) : (string) $raw;
                $valueToFields[$key] = $valueToFields[$key] ?? [];
                $valueToFields[$key][] = $field;
            }

            foreach ($valueToFields as $rawKey => $fieldsWithSameValue) {
                if (count($fieldsWithSameValue) < 2) {
                    continue;
                }

                $raw = $filters[$fieldsWithSameValue[0]] ?? null;
                if ($raw === null) {
                    continue;
                }

                [$operator, $value] = self::parseFilterValue($raw);

                $baseQuery->where(function ($q) use ($fieldsWithSameValue, $operator, $value, $customFilterCallbacks, $fieldAliases, $fuzzyLikeFields) {
                    foreach ($fieldsWithSameValue as $idx => $field) {
                        $method = $idx === 0 ? 'where' : 'orWhere';
                        $alias = $fieldAliases[$field] ?? $field;
                        if (isset($customFilterCallbacks[$alias]) && is_callable($customFilterCallbacks[$alias])) {
                            ($customFilterCallbacks[$alias])($q, $method, $operator, $value);
                            continue;
                        }
                        self::applyFieldWhere($q, $method, $alias, $operator, $value, $fuzzyLikeFields);
                    }
                });

                // Remove processed filters so they don't AND-ся ниже
                foreach ($fieldsWithSameValue as $f) {
                    unset($filters[$f]);
                }
            }
        }
        foreach ($filters as $field => $raw) {
            $alias = $fieldAliases[$field] ?? $field;
            if (
                !empty($allowedFilters)
                && !in_array($field, $allowedFilters, true)
                && !in_array($alias, $allowedFilters, true)
            ) {
                continue;
            }

            if (is_string($raw) && str_contains($raw, ',') && in_array($field, $betweenFields, true)) {
                // Treat as between for configured fields
                [$start, $end] = array_map('trim', explode(',', $raw, 2) + [null, null]);
                if ($start !== null && $end !== null) {
                    if (str_contains($alias, '.')) {
                        self::applyFieldBetween($baseQuery, $alias, $start, $end);
                    } else {
                        $baseQuery->whereBetween($alias, [$start, $end]);
                    }
                    continue;
                }
            }

            // whereIn via comma-separated values
            if (is_string($raw) && str_contains($raw, ',') && $raw !== '*,*') {
                $values = collect(explode(',', $raw))
                    ->map(fn($v) => trim((string) $v))
                    ->filter(fn($v) => $v !== '')
                    ->all();
                if (!empty($values)) {
                    if (!str_contains($alias, '.')) {
                        $baseQuery->whereIn($alias, $values);
                    } else {
                        // Fallback: emulate IN as OR of equals inside relation
                        self::applyFieldWhere($baseQuery, 'where', $alias, 'in', $values);
                    }
                    continue;
                }
            }

            [$operator, $value] = self::parseFilterValue($raw);

            if (isset($customFilterCallbacks[$alias]) && is_callable($customFilterCallbacks[$alias])) {
                ($customFilterCallbacks[$alias])($baseQuery, 'where', $operator, $value);
                continue;
            }

            self::applyFieldWhere($baseQuery, 'where', $alias, $operator, $value, $fuzzyLikeFields);
        }

        // Global search fallback: ?search=... → OR across $searchableFields
        $search = trim((string) $request->get('search', ''));
        if ($search !== '' && !empty($searchableFields)) {
            $baseQuery->where(function ($q) use ($searchableFields, $search, $fieldAliases, $fuzzyLikeFields) {
                foreach ($searchableFields as $idx => $field) {
                    $method = $idx === 0 ? 'where' : 'orWhere';
                    $alias = $fieldAliases[$field] ?? $field;
                    self::applyFieldWhere($q, $method, $alias, 'like', $search, $fuzzyLikeFields);
                }
            });
        }

        // Sorting
        $sort = (string) $request->get('sort', '');
        if ($sort !== '') {
            $parts = array_filter(explode(',', $sort));
            foreach ($parts as $p) {
                $direction = str_starts_with($p, '-') ? 'desc' : 'asc';
                $column = ltrim($p, '-');
                if (empty($allowedSorts) || in_array($column, $allowedSorts, true)) {
                    $baseQuery->orderBy($column, $direction);
                }
            }
        }

        // Optional hard limit support for controllers that supported it before
        $limit = $request->get('limit');
        if ($limit !== null && is_numeric($limit)) {
            $baseQuery->limit((int) $limit);
        }

        return $baseQuery->paginate($perPage);
    }

    /**
     * Apply where (or orWhere) to a possibly-related field path like "user.name" or "goods.specifications.size".
     * Supports LIKE via wildcards and can emulate IN via OR of equals.
     */
    private static function applyFieldWhere($query, string $method, string $field, string $operator, $value, array $fuzzyLikeFields = []): void
    {
        // Normalize operator for fuzzy-like fields
        if ($operator === '=' && in_array($field, $fuzzyLikeFields, true) && is_string($value)) {
            $operator = 'like';
        }

        // Emulate IN
        if (strtolower($operator) === 'in' && is_array($value)) {
            // OR of equals
            $query->{$method}(function ($sub) use ($field, $value) {
                foreach ($value as $idx => $v) {
                    $m = $idx === 0 ? 'where' : 'orWhere';
                    self::applyFieldWhere($sub, $m, $field, '=', $v);
                }
            });
            return;
        }

        if (!str_contains($field, '.')) {
            if (strtolower($operator) === 'like') {
                $query->{$method}($field, 'LIKE', self::buildLikePattern((string) $value));
            } else {
                $query->{$method}($field, $operator, $value);
            }
            return;
        }

        [$relations, $column] = self::splitRelationPath($field);
        // Wrap OR-branch
        if ($method === 'orWhere') {
            $query->orWhere(function ($sub) use ($relations, $column, $operator, $value) {
                self::applyRelationWhere($sub, $relations, $column, $operator, $value);
            });
        } else {
            self::applyRelationWhere($query, $relations, $column, $operator, $value);
        }
    }

    private static function splitRelationPath(string $path): array
    {
        $parts = explode('.', $path);
        $column = array_pop($parts);
        return [$parts, $column];
    }

    private static function applyRelationWhere($query, array $relations, string $column, string $operator, $value): void
    {
        $current = array_shift($relations);
        $query->whereHas($current, function ($nested) use ($relations, $column, $operator, $value) {
            if (empty($relations)) {
                if (strtolower($operator) === 'like') {
                    $nested->where($column, 'LIKE', self::buildLikePattern((string) $value));
                } else {
                    $nested->where($column, $operator, $value);
                }
                return;
            }
            self::applyRelationWhere($nested, $relations, $column, $operator, $value);
        });
    }

    private static function applyFieldBetween($query, string $field, $start, $end): void
    {
        if (!str_contains($field, '.')) {
            $query->whereBetween($field, [$start, $end]);
            return;
        }

        [$relations, $column] = self::splitRelationPath($field);
        $query->whereHas(array_shift($relations), function ($nested) use ($relations, $column, $start, $end) {
            if (empty($relations)) {
                $nested->whereBetween($column, [$start, $end]);
                return;
            }
            self::applyFieldBetween($nested, implode('.', $relations) . '.' . $column, $start, $end);
        });
    }

    private static function parseFilterValue($raw): array
    {
        $value = is_string($raw) ? $raw : (string) $raw;

        if ($value !== '' && $value[0] === '!') {
            return ['!=', substr($value, 1)];
        }

        if (preg_match('/^(>=|<=|>|<)(.+)$/', $value, $m)) {
            return [$m[1], trim($m[2])];
        }

        if (str_contains($value, '*') || str_contains($value, '?')) {
            return ['like', $value];
        }

        return ['=', $value];
    }

    public static function wildcardToLike(string $value): string
    {
        return str_replace(['*', '?'], ['%', '_'], $value);
    }

    public static function buildLikePattern(string $value): string
    {
        // If user provided wildcards, convert to SQL pattern as-is
        if (str_contains($value, '*') || str_contains($value, '?')) {
            return self::wildcardToLike($value);
        }
        // Otherwise do a fuzzy contains search
        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $value);
        return "%{$escaped}%";
    }
}


