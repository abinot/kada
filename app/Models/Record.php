<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Record extends Model
{
    /** @use HasFactory<\Database\Factories\RecordFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'note',
        'created_by',
        'import_batch_id', 
        'import_source'
    ];




    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ---------- روابط ----------

    /**
     * پروژه‌هایی که این رکورد در آن‌ها حضور دارد
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_record')
            ->withPivot(['added_by', 'is_active'])
            ->withTimestamps();
    }

    /**
     * همه‌ی مقادیر (ریشه + فرزندان)
     */
    public function values(): HasMany
    {
        return $this->hasMany(RecordValue::class);
    }

    /**
     * فقط ریشه‌ها (والد ندارند)
     */
    public function rootValues(): HasMany
    {
        return $this->values()
            ->whereNull('parent_id')
            ->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---------- API داده ----------

    /**
     * همه‌ی مقادیر به صورت آرایه‌ی تودرتو
     * استفاده: $record->data
     *
     * خروجی نمونه:
     * [
     *   'name' => [
     *     'value' => 'رضا',
     *     'prefix_name' => 'دکتر',
     *     'mean_of_name' => '...',
     *   ],
     *   'age' => 30,
     * ]
     */
    public function getDataAttribute(): array
    {
        $roots = $this->relationLoaded('values')
            ? $this->values->whereNull('parent_id')->sortBy('sort_order')
            : $this->rootValues()->with('descendants')->get();

        return $roots->mapWithKeys(function (RecordValue $root) {
            return [$root->key => $this->nodeToArray($root)];
        })->all();
    }

    /**
     * فقط برگ‌ها با مسیر کامل
     * استفاده: $record->flatData
     *
     * خروجی: ['name' => 'رضا', 'name.prefix_name' => 'دکتر']
     */
    public function getFlatDataAttribute(): array
    {
        $result = [];

        foreach ($this->rootValues as $root) {
            $result = array_merge($result, $root->flattenTree());
        }

        return $result;
    }

    /**
     * خواندن یک مقدار با مسیر کامل
     * استفاده: $record->getValue('name')
     *          $record->getValue('name.prefix_name')
     */
    public function getValue(string $path, mixed $default = null): mixed
    {
        $parts = explode('.', $path);

        $current = $this->rootValues()
            ->where('key', array_shift($parts))
            ->first();

        if (! $current) {
            return $default;
        }

        foreach ($parts as $part) {
            $current = $current->children()->where('key', $part)->first();
            if (! $current) {
                return $default;
            }
        }

        return $current->typedValue() ?? $default;
    }

    /**
     * ست کردن یک مقدار (بدون ذخیره)
     * استفاده: $record->setValue('name', 'علی')
     *          $record->setValue('name.prefix_name', 'دکتر')
     */
    public function setValue(string $path, mixed $value, string $type = 'auto'): self
    {
        $parts = explode('.', $path);

        $parentId = null;
        foreach ($parts as $index => $part) {
            $isLast = ($index === count($parts) - 1);

            $existing = $this->values()
                ->where('parent_id', $parentId)
                ->where('key', $part)
                ->first();

            if ($isLast) {
                if ($existing) {
                    $existing->updateValue($value, $type);
                } else {
                    $node = $this->values()->create([
                        'parent_id' => $parentId,
                        'key' => $part,
                        'value_type' => $type !== 'auto' ? $type : $this->detectType($value),
                    ]);
                    $node->updateValue($value, $type);
                }
            } else {
                if (! $existing) {
                    $existing = $this->values()->create([
                        'parent_id' => $parentId,
                        'key' => $part,
                        'value_type' => 'string',
                    ]);
                }
                $parentId = $existing->id;
            }
        }

        return $this;
    }

    /**
     * همگام‌سازی همه‌ی مقادیر با آرایه
     * استفاده: $record->syncValues(['name' => 'علی', 'age' => 30])
     *          $record->syncValues(['name' => ['value' => 'علی', 'prefix_name' => 'دکتر']])
     */
public function syncValues(array $data, ?int $parentId = null): void
{
    foreach ($data as $key => $value) {
        // آیا مقدار خالی است؟
        $isEmpty = $value === null || $value === '' || $value === [];

        // اگر آرایه است و مقدار typed نیست (یعنی فرزندان دارد)
        if (is_array($value) && ! $this->isTypedArray($value)) {
            $node = $this->values()
                ->where('parent_id', $parentId)
                ->where('key', $key)
                ->first();

            if (! $node) {
                $node = $this->values()->create([
                    'parent_id' => $parentId,
                    'key' => $key,
                    'value_type' => 'string',
                ]);
            }

            // اگر خود مقدار 'value' دارد
            if (array_key_exists('value', $value)) {
                $node->updateValue($value['value']);
            }

            $children = $value;
            unset($children['value']);

            // اگر فرزندی نماند و مقدار هم خالی است، کل node را حذف کن
            if (empty($children) && (! isset($value['value']) || $value['value'] === null || $value['value'] === '')) {
                $node->delete();
                continue;
            }

            $this->syncValues($children, $node->id);

            continue;
        }

        // مقدار ساده
        if ($isEmpty) {
            // حذف node موجود (اگر هست)
            $this->values()
                ->where('parent_id', $parentId)
                ->where('key', $key)
                ->delete();

            continue;
        }

        // مقدار غیرخالی
        $node = $this->values()
            ->where('parent_id', $parentId)
            ->where('key', $key)
            ->first();

        if ($node) {
            $node->updateValue($value);
        } else {
            $this->values()->create([
                'parent_id' => $parentId,
                'key' => $key,
                'value_type' => $this->detectType($value),
            ])->updateValue($value);
        }
    }
}
    /**
     * حذف یک مقدار با مسیر
     */
    public function removeValue(string $path): void
    {
        $parts = explode('.', $path);

        $current = $this->rootValues()
            ->where('key', array_shift($parts))
            ->first();

        if (! $current) {
            return;
        }

        foreach ($parts as $part) {
            $current = $current->children()->where('key', $part)->first();
            if (! $current) {
                return;
            }
        }

        $current->delete();
    }

    // ---------- internal ----------

    protected function nodeToArray(RecordValue $node): mixed
    {
        $children = $node->relationLoaded('children')
            ? $node->children
            : $node->children()->with('descendants')->get();

        $value = $node->typedValue();

        if ($children->isEmpty()) {
            return $value;
        }

        $array = ['value' => $value];

        foreach ($children as $child) {
            $array[$child->key] = $this->nodeToArray($child);
        }

        return $array;
    }

    /**
     * تشخیص اینکه آرایه یک مقدار typed است یا مجموعه فرزندان
     * (مثلاً value_type=json یک آرایه ذخیره می‌کند)
     */
    protected function isTypedArray(array $value): bool
    {
        return isset($value['__type']);
    }

    protected function detectType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'decimal',
            $value instanceof \DateTimeInterface => 'datetime',
            is_array($value) => 'json',
            default => 'string',
        };
    }

    // ---------- boot ----------

    protected static function booted(): void
    {
        static::creating(function (Record $record) {
            if (empty($record->created_by) && auth()->check()) {
                $record->created_by = auth()->id();
            }
        });
    }

    public function importBatch(): BelongsTo
{
    return $this->belongsTo(ImportBatch::class);
}
}