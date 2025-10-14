<?php

namespace Iafilin\EloquentHttpAdapter\Server;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HttpResponder
{
    public function paginated(Request $request, EloquentBuilder $builder): JsonResponse
    {
        $pageName = config('eloquent-http-adapter.pagination.page_name', 'page');
        $perPageName = config('eloquent-http-adapter.pagination.per_page_name', 'per_page');

        $perPage = (int) $request->query($perPageName, config('eloquent-http-adapter.pagination.default_per_page', 15));
        $page = (int) $request->query($pageName, 1);

        /** @var LengthAwarePaginator $paginator */
        $paginator = $builder->paginate($perPage, ['*'], $pageName, $page);

        return response()->json([
            config('eloquent-http-adapter.response.data_key', 'data') => $paginator->items(),
            config('eloquent-http-adapter.response.total_key', 'total') => $paginator->total(),
            config('eloquent-http-adapter.response.per_page_key', 'per_page') => $paginator->perPage(),
            config('eloquent-http-adapter.response.current_page_key', 'current_page') => $paginator->currentPage(),
        ]);
    }

    public function collection(EloquentBuilder $builder): JsonResponse
    {
        $items = $builder->get();
        return response()->json([
            config('eloquent-http-adapter.response.data_key', 'data') => $items,
            config('eloquent-http-adapter.response.total_key', 'total') => count($items),
            config('eloquent-http-adapter.response.per_page_key', 'per_page') => count($items),
            config('eloquent-http-adapter.response.current_page_key', 'current_page') => 1,
        ]);
    }
}


