<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecordValue extends Model
{
    /** @use HasFactory<\Database\Factories\RecordValueFactory> */
    use HasFactory;

    protected $fillable = [
        'record_id',
        'parent_id',
        'key',
        'label',
        'value_type',
        'value_string',
        'value_text',
        'value_number',
        'value_date',
        'value_boolean',
        'sort_order',
        'kind',
    ];

    protected $casts = [
        'value_number' => 'decimal:6',
        'value_date' => 'datetime',
        'value_boolean' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ---------- روابط ----------

    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RecordValue::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(RecordValue::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * همه‌ی فرزندان به صورت بازگشتی
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    // ---------- مقدار ----------

    /**
     * مقدار typed بر اساس value_type
     */
    public function typedValue(): mixed
    {
        return match ($this->value_type) {
            'integer' => $this->value_number !== null ? (int) $this->value_number : null,
            'decimal' => $this->value_number,
            'date', 'datetime' => $this->value_date,
            'boolean' => $this->value_boolean,
            'text', 'json' => $this->value_text,
            'relation' => $this->value_string !== null ? (int) $this->value_string : null,
            default => $this->value_string,
        };
    }

    /**
     * به‌روزرسانی مقدار (تشخیص نوع خودکار یا صریح)
     */
    public function updateValue(mixed $value, string $type = 'auto'): void
    {
        if ($value === null || $value === '' || $value === []) {
            $this->update([
                'value_string' => null,
                'value_text' => null,
                'value_number' => null,
                'value_date' => null,
                'value_boolean' => null,
            ]);
            return;
        }

        $resolvedType = $type !== 'auto' ? $type : $this->detectType($value);

        $row = [
            'value_type' => $resolvedType,
            'value_string' => null,
            'value_text' => null,
            'value_number' => null,
            'value_date' => null,
            'value_boolean' => null,
        ];

        switch ($resolvedType) {
            case 'integer':
            case 'decimal':
                $row['value_number'] = $value;
                break;

            case 'date':
            case 'datetime':
                $row['value_date'] = $value;
                break;

            case 'boolean':
                $row['value_boolean'] = (bool) $value;
                break;

            case 'text':
            case 'json':
                $row['value_text'] = is_string($value) ? $value : json_encode($value);
                break;

            case 'relation':
                $row['value_string'] = (string) $value;
                break;

            default: // string
                $str = is_array($value) ? json_encode($value) : (string) $value;

                if (mb_strlen($str) > 255) {
                    $row['value_text'] = $str;
                    $row['value_type'] = 'text';
                } else {
                    $row['value_string'] = $str;
                }
                break;
        }

        $this->update($row);
    }

    // ---------- درخت ----------

    /**
     * آیا برگ است؟ (فرزند ندارد)
     */
    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    /**
     * آیا ریشه است؟ (والد ندارد)
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * مسیر کامل از ریشه تا این گره
     * مثال: "name.prefix_name"
     */
    public function fullPath(string $separator = '.'): string
    {
        $parts = [$this->key];
        $current = $this;

        while ($current->parent_id !== null) {
            $current = $current->parent()->first();
            if (! $current) break;
            array_unshift($parts, $current->key);
        }

        return implode($separator, $parts);
    }

    /**
     * عمق گره در درخت (ریشه = 0)
     */
    public function depth(): int
    {
        $depth = 0;
        $current = $this;

        while ($current->parent_id !== null) {
            $current = $current->parent()->first();
            if (! $current) break;
            $depth++;
        }

        return $depth;
    }

    /**
     * خروجی آرایه‌ای تودرتو
     */
    public function toArrayTree(): array
    {
        $children = $this->relationLoaded('children')
            ? $this->children
            : $this->children()->get();

        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'kind' => $this->kind,
            'type' => $this->value_type,
            'value' => $this->typedValue(),
            'children' => $children->map(fn ($child) => $child->toArrayTree())->all(),
        ];
    }

    /**
     * همه‌ی برگ‌ها با مسیر کامل
     * مثال: ['name' => 'رضا', 'name.prefix_name' => 'دکتر']
     */
    public function flattenTree(): array
    {
        $result = [];

        $this->walk(function (RecordValue $node, string $path) use (&$result) {
            if ($node->children->isEmpty()) {
                $result[$path] = $node->typedValue();
            }
        });

        return $result;
    }

    protected function walk(callable $callback, string $prefix = ''): void
    {
        $path = $prefix === '' ? $this->key : $prefix.'.'.$this->key;

        if (! $this->relationLoaded('children')) {
            $this->load('children');
        }

        $callback($this, $path);

        foreach ($this->children as $child) {
            $child->walk($callback, $path);
        }
    }

    // ---------- internal ----------

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
}