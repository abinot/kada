<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_record', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('record_id')
                ->constrained()
                ->cascadeOnDelete();

            // چه کسی این رکورد را به این پروژه اضافه کرد
            $table->foreignId('added_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // آیا این رکورد در این پروژه فعال است؟
            // (برای مواقعی که می‌خواهی مخفی کنی بدون حذف)
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // هر رکورد در هر پروژه فقط یک بار
            $table->unique(['project_id', 'record_id'], 'project_record_unique');

            // ایندکس برای لیست رکوردهای یک پروژه
            $table->index(['project_id', 'created_at'], 'pr_project_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_record');
    }
};