<?php

use App\Http\Controllers\SearchPageController;
use Illuminate\Support\Facades\Route;

Route::get('/search', [SearchPageController::class, 'index'])
    ->name('search.index');
