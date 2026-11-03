<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_api_usage_counters', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();

            $table->unsignedBigInteger('usage_count')->default(0);
        });

        DB::table('search_api_usage_counters')->insert([
            'id' => 1,
            'usage_count' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('search_api_usage_counters');
    }
};
