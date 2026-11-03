
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_record_selections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('search_record_id')
                ->unique()
                ->constrained('search_records')
                ->cascadeOnDelete();

            $table->timestamp('selected_at')->useCurrent();
        });

        // Start fresh with a zero usage count.
        DB::table('search_api_usage_counters')
            ->where('id', 1)
            ->update([
                'usage_count' => 0,
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('search_record_selections');
    }
};
