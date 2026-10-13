<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->foreignId('import_batch_id')
                ->nullable()
                ->after('created_by')
                ->constrained('import_batches')
                ->nullOnDelete();

            $table->string('import_source', 100)
                ->nullable()
                ->after('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
            $table->dropColumn(['import_batch_id', 'import_source']);
        });
    }
};