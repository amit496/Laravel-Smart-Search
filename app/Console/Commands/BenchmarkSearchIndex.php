<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class BenchmarkSearchIndex extends Command
{
    protected $signature = 'search:benchmark-index
                            {--limit=100000 : Number of source records}
                            {--batch=500 : Records per batch}
                            {--reset : Recreate the temporary test table}';

    protected $description = 'Build and benchmark a temporary trigram index';

    private const TABLE = 'search_trigram_test';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $batchSize = max(1, min(1000, (int) $this->option('batch')));

        try {
            if (Schema::hasTable(self::TABLE)) {
                if (! $this->option('reset')) {
                    $this->error(
                        'Temporary table exists. Use --reset to recreate it.'
                    );

                    return self::FAILURE;
                }

                Schema::drop(self::TABLE);
            }

            Schema::create(self::TABLE, function ($table) {
                $table->string('trigram', 3);
                $table->unsignedBigInteger('search_record_id');

                $table->index(
                    ['trigram', 'search_record_id'],
                    'trigram_record_idx'
                );
            });

            $total = DB::table('search_records')->count();
            $limit = min($limit, $total);

            $this->info("Source records: {$total}");
            $this->info("Benchmark records: {$limit}");
            $this->info("Batch size: {$batchSize}");

            $lastId = 0;
            $processed = 0;
            $inserted = 0;

            $start = microtime(true);

            while ($processed < $limit) {
                $remaining = $limit - $processed;

                $records = DB::table('search_records')
                    ->select('id', 'content')
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit(min($batchSize, $remaining))
                    ->get();

                if ($records->isEmpty()) {
                    break;
                }

                $batchRows = [];

                foreach ($records as $record) {
                    $lastId = (int) $record->id;

                    $content = mb_strtolower((string) $record->content);
                    $length = mb_strlen($content);

                    $unique = [];

                    for ($i = 0; $i <= $length - 3; $i++) {
                        $gram = mb_substr($content, $i, 3);

                        $unique[$gram] = true;
                    }

                    foreach (array_keys($unique) as $gram) {
                        $batchRows[] = [
                            'trigram' => $gram,
                            'search_record_id' => $record->id,
                        ];
                    }

                    $processed++;
                }

                foreach (array_chunk($batchRows, 1000) as $chunk) {
                    DB::table(self::TABLE)->insert($chunk);
                    $inserted += count($chunk);
                }

                $this->line(
                    "Processed {$processed}/{$limit} records; "
                    . number_format($inserted) . ' index entries'
                );

                unset($records, $batchRows);
            }

            $buildMs = round((microtime(true) - $start) * 1000, 2);

            $this->newLine();
            $this->info('Index build completed.');
            $this->info("Records processed: {$processed}");
            $this->info('Index entries: ' . number_format($inserted));
            $this->info("Build time: {$buildMs} ms");

            foreach (['lak', 'ake', 'lake'] as $term) {
                $queryStart = microtime(true);

                $grams = [];

                for ($i = 0; $i <= mb_strlen($term) - 3; $i++) {
                    $grams[] = mb_substr($term, $i, 3);
                }

                $candidateIds = DB::table(self::TABLE)
                    ->select('search_record_id')
                    ->whereIn('trigram', array_unique($grams))
                    ->groupBy('search_record_id')
                    ->havingRaw(
                        'COUNT(DISTINCT trigram) = ?',
                        [count(array_unique($grams))]
                    )
                    ->limit(1000)
                    ->pluck('search_record_id');

                $verified = 0;

                if ($candidateIds->isNotEmpty()) {
                    $verified = DB::table('search_records')
                        ->whereIn('id', $candidateIds)
                        ->whereRaw('LOWER(content) LIKE ?', [
                            '%' . $term . '%',
                        ])
                        ->count();
                }

                $queryMs = round(
                    (microtime(true) - $queryStart) * 1000,
                    2
                );

                $this->line(
                    "{$term}: {$verified} verified matches; "
                    . "{$queryMs} ms"
                );
            }

            $this->newLine();

            $size = DB::selectOne(
                "SELECT
                    ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = ?",
                [self::TABLE]
            );

            $this->info(
                'Temporary index size: ' . ($size->size_mb ?? 'unknown') . ' MB'
            );

            $this->warn(
                'Temporary table retained. Existing source data was not modified.'
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            $this->warn(
                'Temporary table may be incomplete. Source records were not modified.'
            );

            return self::FAILURE;
        }
    }
}
