<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class SearchPageController extends Controller
{
    public function index()
    {
        $usageCount = DB::table('search_api_usage_counters')
            ->where('id', 1)
            ->value('usage_count');

        if ($usageCount === null) {
            throw new RuntimeException(
                'The search usage counter record is missing.'
            );
        }

        return view('search', [
            'usageCount' => (int) $usageCount,
        ]);
    }
}
