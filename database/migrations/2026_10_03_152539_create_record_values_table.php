<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_values', function (Blueprint $table) {
            $table->id();

            // به کدام رکورد تعلق دارد (روی همه‌ی ردیف‌های درخت یکی است)
            $table->foreignId('record_id')
                ->constrained()
                ->cascadeOnDelete();

            // والد در درخت — اگر null باشد، ریشه است
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('record_values')
                ->cascadeOnDelete();

            // کلید و برچسب
            $table->string('key', 100);
            $table->string('label', 255)->nullable();

            // نوع مقدار
            // مقادیر مجاز: string, text, integer, decimal, boolean, date, datetime, json, relation
            $table->string('value_type', 20)->default('string');

            // ستون‌های typed — بر اساس value_type یکی پر می‌شود
            $table->string('value_string', 255)->nullable();   // string, select, relation (id)
            $table->text('value_text')->nullable();            // text, json
            $table->decimal('value_number', 20, 6)->nullable(); // integer, decimal
            $table->dateTime('value_date')->nullable();         // date, datetime
            $table->boolean('value_boolean')->nullable();       // boolean

            // ترتیب بین هم‌نیاها (زیر یک والد)
            $table->integer('sort_order')->default(0);

            // گروه‌بندی معنایی
            // field  → مقدار عادی
            // meta   → متادیتا (مثلاً تاریخ آخرین ویرایش)
            // relation → اشاره به رکورد دیگر
            // translation → ترجمه یا معنی
            $table->string('kind', 30)->default('field');

            $table->timestamps();

            // کلید در یک رکورد و زیر یک والد خاص یکتاست
            $table->unique(['record_id', 'parent_id', 'key'], 'rv_unique_key');

            // ایندکس‌ها برای کارایی
            $table->index(['record_id', 'parent_id'], 'rv_parent_idx');
            $table->index(['record_id', 'key'], 'rv_record_key_idx');
            $table->index('key', 'rv_key_idx');
            $table->index(['key', 'value_string'], 'rv_string_idx');
            $table->index(['key', 'value_number'], 'rv_number_idx');
            $table->index(['key', 'value_date'], 'rv_date_idx');
            $table->index(['key', 'value_boolean'], 'rv_bool_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_values');
    }
};