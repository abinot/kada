<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // نام اختیاری برای رکورد (مثلاً «رضا بهرامیان»)
            $table->string('title')->nullable();

            // یادداشت داخلی
            $table->text('note')->nullable();

            // اگر بخواهی بدانی چه کسی ساخت
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};