
<?php

use App\Http\Controllers\Api\FastSearchController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/search', [SearchController::class, 'index'])
    ->middleware('throttle:120,1');

Route::post('/search/{search_record}/select', [SearchController::class, 'select'])
    ->middleware([
        'signed:relative',
        'throttle:60,1',
    ])
    ->name('api.search.select');

Route::get('/fast-search', [FastSearchController::class, 'index'])
    ->middleware('throttle:120,1');
