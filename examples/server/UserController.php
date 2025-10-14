<?php

namespace Examples\Server;

use Iafilin\EloquentHttpAdapter\Server\HttpResponder;
use Iafilin\EloquentHttpAdapter\Server\QueryApplier;
use Illuminate\Http\Request;

class UserController
{
    public function index(Request $request, QueryApplier $applier, HttpResponder $responder)
    {
        $builder = \App\Models\User::query();
        $builder = $applier->apply($request, $builder);
        return $responder->paginated($request, $builder);
    }
}


