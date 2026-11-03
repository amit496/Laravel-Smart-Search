<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SearchRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class SearchController extends Controller
{
    private const PAGE_SIZE = 10;
    private const CACHE_TTL = 300;

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $normalizedSearch = mb_strtolower(
            preg_replace('/\s+/u', ' ', $search) ?? $search
        );
        $page = max(1, (int) $request->query('page', 1));

        $startTime = microtime(true);
        $timings = [];
        $fetchTimings = [];

        try {
            // Versioned cache key prevents old cached response structures being reused.
            $cacheKey = 'search:v3:' . md5($normalizedSearch) . ':' . $page;

            $stepStart = microtime(true);
            $result = Cache::store('file')->remember(
                $cacheKey,
                self::CACHE_TTL,
                function () use ($normalizedSearch, $page, &$fetchTimings): array {
                    return $this->fetch($normalizedSearch, $page, $fetchTimings);
                }
            );
            $timings['cache_ms'] = round((microtime(true) - $stepStart) * 1000, 2);

            // Signed selection URLs are generated per response; they are not cached.
            $stepStart = microtime(true);
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
            $timings['url_generation_ms'] = round((microtime(true) - $stepStart) * 1000, 2);

            // Reading the counter does not increment it. Only select() increments it.
            $stepStart = microtime(true);
            $usageCount = DB::table('search_api_usage_counters')
                ->where('id', 1)
                ->value('usage_count');
            $timings['usage_counter_ms'] = round((microtime(true) - $stepStart) * 1000, 2);

            if ($usageCount === null) {
                throw new \RuntimeException('The search usage counter record is missing.');
            }
        } catch (Throwable $exception) {
            Log::error('Autocomplete search failed.', [
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
            'timings' => array_merge($timings, $fetchTimings),
            'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ]);
    }

    public function select(Request $request, SearchRecord $searchRecord): JsonResponse
    {
        try {
            $recorded = DB::transaction(function () use ($searchRecord): bool {
                // The unique search_record_id constraint ensures one count per record.
                $inserted = DB::table('search_record_selections')->insertOrIgnore([
                    'search_record_id' => $searchRecord->id,
                    'selected_at' => now(),
                ]);

                if ($inserted === 1) {
                    $updated = DB::table('search_api_usage_counters')
                        ->where('id', 1)
                        ->increment('usage_count');

                    if ($updated !== 1) {
                        throw new \RuntimeException('The search usage counter record is missing.');
                    }

                    return true;
                }

                return false;
            });

            $usageCount = DB::table('search_api_usage_counters')
                ->where('id', 1)
                ->value('usage_count');

            if ($usageCount === null) {
                throw new \RuntimeException('The search usage counter record is missing.');
            }

            return response()->json([
                'success' => true,
                'recorded' => $recorded,
                'selected' => true,
                'usage_count' => (int) $usageCount,
            ]);
        } catch (Throwable $exception) {
            Log::error('Suggestion selection could not be recorded.', [
                'search_record_id' => $searchRecord->id,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Selection is temporarily unavailable.',
            ], 503);
        }
    }

    /**
     * @return array{rows: array<int, array{id: int, content: string}>, has_more: bool}
     */
    private function fetch(string $search, int $page, array &$fetchTimings): array
    {
        $query = DB::table('search_records')->select('id', 'content');

        if ($search !== '') {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $query->where('content', 'like', '%' . $search . '%');
            } else {
                // Preserve the existing SearchController FULLTEXT semantics:
                // words shorter than 3 characters are ignored and the last word is a prefix.
                $booleanQuery = $this->makeBooleanQuery($search);

                if ($booleanQuery === '') {
                    $fetchTimings['database_query_ms'] = 0;

                    return ['rows' => [], 'has_more' => false];
                }

                $query->whereRaw('MATCH(content) AGAINST (? IN BOOLEAN MODE)', [$booleanQuery]);
            }
        }

        $stepStart = microtime(true);
        $rows = $query
            ->orderBy('id')
            ->limit(self::PAGE_SIZE + 1)
            ->offset(($page - 1) * self::PAGE_SIZE)
            ->get();

        $fetchTimings['database_query_ms'] = round((microtime(true) - $stepStart) * 1000, 2);

        return [
            'rows' => $rows->take(self::PAGE_SIZE)->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'content' => $row->content,
            ])->all(),
            'has_more' => $rows->count() > self::PAGE_SIZE,
        ];
    }

    private function makeBooleanQuery(string $search): string
    {
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $search);

        if ($cleaned === null) {
            return '';
        }

        $words = preg_split('/\s+/u', trim($cleaned), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_slice(
            array_values(array_unique(array_filter(
                $words,
                fn (string $word): bool => mb_strlen($word) >= 3
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
