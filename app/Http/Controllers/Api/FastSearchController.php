<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

class FastSearchController extends Controller
{
    private const PAGE_SIZE = 10;
    private const CACHE_TTL = 300;

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $page = max(1, (int) $request->query('page', 1));

        $startTime = microtime(true);
        $fetchTimings = [];

        if (mb_strlen($search) < 2) {
            return response()->json([
                'success' => true,
                'data' => [],
                'has_more' => false,
                'usage_count' => (int) DB::table('search_api_usage_counters')
                    ->where('id', 1)
                    ->value('usage_count'),
                'execution_time_ms' => round(
                    (microtime(true) - $startTime) * 1000,
                    2
                ),
            ]);
        }

        try {
            $cacheKey = 'fast-search:v2:'
                . md5(mb_strtolower($search))
                . ':' . $page;

            $result = Cache::store('file')->remember(
                $cacheKey,
                self::CACHE_TTL,
                function () use ($search, $page, &$fetchTimings) {
                    return $this->fetch($search, $page, $fetchTimings);
                }
            );

            $expiresAt = now()->addMinutes(10);

            $records = array_map(
                fn (array $record): array => [
                    ...$record,
                    'selection_url' => URL::temporarySignedRoute(
                        'api.search.select',
                        $expiresAt,
                        ['search_record' => $record['id']],
                        false
                    ),
                ],
                $result['rows']
            );

            $usageCount = DB::table('search_api_usage_counters')
                ->where('id', 1)
                ->value('usage_count');

            if ($usageCount === null) {
                throw new RuntimeException(
                    'The search usage counter record is missing.'
                );
            }
        } catch (Throwable $exception) {
            Log::error('Fast autocomplete search failed.', [
                'query' => $search,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Search is temporarily unavailable.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => $records,
            'has_more' => $result['has_more'],
            'usage_count' => (int) $usageCount,
            'timings' => $fetchTimings,
            'execution_time_ms' => round(
                (microtime(true) - $startTime) * 1000,
                2
            ),
        ]);
    }

    private function fetch(
        string $search,
        int $page,
        array &$fetchTimings
    ): array {
        $normalized = mb_strtolower(
            preg_replace('/\s+/u', ' ', $search) ?? $search
        );

        $driver = DB::connection()->getDriverName();

        $stepStart = microtime(true);

        $query = DB::table('search_records')
            ->select('id', 'content');

        if ($driver === 'sqlite') {
            $query->where('content', 'like', '%' . $normalized . '%');
        } else {
            // Current FULLTEXT fallback: token/prefix matching only.
            // This is NOT arbitrary substring matching.
            $booleanQuery = $this->makeBooleanQuery($normalized);

            if ($booleanQuery === '') {
                return [
                    'rows' => [],
                    'has_more' => false,
                ];
            }

            $query->whereRaw(
                'MATCH(content) AGAINST (? IN BOOLEAN MODE)',
                [$booleanQuery]
            );
        }

        $rows = $query
            ->limit(self::PAGE_SIZE + 1)
            ->offset(($page - 1) * self::PAGE_SIZE)
            ->get();

        $fetchTimings['database_query_ms'] = round(
            (microtime(true) - $stepStart) * 1000,
            2
        );

        return [
            'rows' => $rows
                ->take(self::PAGE_SIZE)
                ->map(fn (object $row): array => [
                    'id' => (int) $row->id,
                    'content' => $row->content,
                ])
                ->all(),
            'has_more' => $rows->count() > self::PAGE_SIZE,
        ];
    }

    private function makeBooleanQuery(string $search): string
    {
        $cleaned = preg_replace(
            '/[^\p{L}\p{N}\s]+/u',
            ' ',
            $search
        );

        if ($cleaned === null) {
            return '';
        }

        $words = preg_split(
            '/\s+/u',
            trim($cleaned),
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        $words = array_slice(
            array_values(array_unique(array_filter(
                $words,
                fn (string $word): bool => mb_strlen($word) >= 2
            ))),
            0,
            5
        );

        if ($words === []) {
            return '';
        }

        $words[array_key_last($words)] .= '*';

        return implode(' ', $words);
    }
}
