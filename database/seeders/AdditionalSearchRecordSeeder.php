<?php

namespace Database\Seeders;

use App\Models\SearchRecord;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdditionalSearchRecordSeeder extends Seeder
{
    private const TOTAL_RECORDS = 5000000;

    private const BATCH_SIZE = 1000;

    public function run(): void
    {
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

            if (count($batch) === self::BATCH_SIZE) {
                SearchRecord::insert($batch);
                $inserted += count($batch);
                $batch = [];

                if ($inserted % 100000 === 0) {
                    $this->command->info(
                        number_format($inserted).' additional records inserted.'
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
                'Expected to insert 5 million records, but generated '.
                number_format($inserted)
            );
        }

        $this->command->info(
            'Successfully inserted '.
            number_format($inserted).
            ' additional address records.'
        );
    }
}
