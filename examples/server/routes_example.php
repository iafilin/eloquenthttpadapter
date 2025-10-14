<?php

use Illuminate\Support\Facades\Route;
use Examples\Server\UserController;

Route::get('/users', [UserController::class, 'index']);


