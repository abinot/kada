<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'team_id',
        'uuid',
        'name',
        'description',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * رکوردهای این پروژه (چند-به-چند)
     */
    public function records(): BelongsToMany
    {
        return $this->belongsToMany(Record::class, 'project_record')
            ->withPivot(['added_by', 'is_active'])
            ->withTimestamps();
    }

    /**
     * فقط رکوردهای فعال این پروژه
     */
    public function activeRecords(): BelongsToMany
    {
        return $this->records()->wherePivot('is_active', true);
    }

    // ---------- متدهای کمکی ----------

    /**
     * همه‌ی کلیدهای استفاده‌شده در این پروژه
     * (برای پیشنهاد key هنگام ساخت رکورد جدید)
     */
    public function usedKeys(): array
    {
        return RecordValue::query()
            ->whereHas('record', function ($q) {
                $q->whereHas('projects', fn ($q) => $q->where('projects.id', $this->id));
            })
            ->distinct()
            ->orderBy('key')
            ->pluck('key')
            ->all();
    }

    /**
     * پرتکرارترین کلیدها در این پروژه
     * خروجی: ['name' => 47, 'phone' => 25, ...]
     */
    public function keyFrequency(int $limit = 20): array
    {
        return RecordValue::query()
            ->whereHas('record', function ($q) {
                $q->whereHas('projects', fn ($q) => $q->where('projects.id', $this->id));
            })
            ->selectRaw('`key`, COUNT(*) as count')
            ->groupBy('key')
            ->orderByDesc('count')
            ->limit($limit)
            ->pluck('count', 'key')
            ->all();
    }
}