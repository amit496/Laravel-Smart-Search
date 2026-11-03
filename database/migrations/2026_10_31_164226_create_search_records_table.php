
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_records', function (Blueprint $table) {
            $table->id();

            $table->text('content');

            if (in_array(
                Schema::getConnection()->getDriverName(),
                ['mysql', 'mariadb', 'pgsql'],
                true
            )) {
                $table->fullText(
                    'content',
                    'search_records_content_fulltext'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_records');
    }
};
