<?php

namespace Iafilin\EloquentHttpAdapter\Server;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HandlesApiIndex
{
    protected QueryApplier $queryApplier;
    protected HttpResponder $httpResponder;

    public function setHttpAdapterServer(QueryApplier $applier, HttpResponder $responder): void
    {
        $this->queryApplier = $applier;
        $this->httpResponder = $responder;
    }

    protected function applyIndexQuery(Request $request, EloquentBuilder $builder): EloquentBuilder
    {
        return $this->queryApplier->apply($request, $builder);
    }

    protected function respondIndex(Request $request, EloquentBuilder $builder): JsonResponse
    {
        $paginated = filter_var($request->query('paginated', 'true'), FILTER_VALIDATE_BOOLEAN);
        if ($paginated) {
            return $this->httpResponder->paginated($request, $builder);
        }
        return $this->httpResponder->collection($builder);
    }
}


