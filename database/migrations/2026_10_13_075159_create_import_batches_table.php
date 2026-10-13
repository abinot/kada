<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // فایل اصلی
            $table->string('original_filename');
            $table->string('format', 20); // json | ndjson | sqlite
            $table->unsignedInteger('record_count')->default(0);
            $table->unsignedBigInteger('file_size')->default(0); // bytes

            // وضعیت
            $table->string('status', 20)->default('completed'); // pending | completed | reverted | failed
            $table->text('error_message')->nullable();

            // متادیتا
            $table->json('metadata')->nullable(); // key_stats، duplicate count، ...

            // ردیابی برگشت
            $table->timestamp('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['team_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};