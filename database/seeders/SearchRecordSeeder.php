<?php

namespace Database\Seeders;

use App\Models\SearchRecord;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use RuntimeException;

class SearchRecordSeeder extends Seeder
{
    private const TOTAL_RECORDS = 40000000;

    private const BATCH_SIZE = 1000;

    public function run(): void
    {
        $existingRecords = SearchRecord::query()->exists();

        if ($existingRecords) {
            $this->command->error(
                'Search records table is not empty. Seeder stopped.'
            );

            return;
        }

        $faker = FakerFactory::create('en_GB');
        $batch = [];
        $inserted = 0;

        while ($inserted < self::TOTAL_RECORDS) {
            $batch[] = [
                'content' => sprintf(
                    '%s, %s, %s',
                    $faker->streetAddress(),
                    $faker->city(),
                    $faker->postcode()
                ),
            ];

            if (count($batch) >= self::BATCH_SIZE) {
                SearchRecord::insert($batch);

                $inserted += count($batch);
                $batch = [];

                if ($inserted % 100000 === 0) {
                    $this->command->info(
                        number_format($inserted).
                        ' records inserted.'
                    );
                }
            }
        }

        if (! empty($batch)) {
            SearchRecord::insert($batch);
            $inserted += count($batch);
        }

        if ($inserted !== self::TOTAL_RECORDS) {
            throw new RuntimeException(
                'Expected 40 million records, but generated '.
                number_format($inserted)
            );
        }

        $this->command->info(
            'Successfully inserted '.
            number_format($inserted).
            ' address records.'
        );
    }
}
