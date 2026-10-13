<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    /** @use HasFactory<\Database\Factories\ImportBatchFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'team_id',
        'project_id',
        'user_id',
        'original_filename',
        'format',
        'record_count',
        'file_size',
        'status',
        'error_message',
        'metadata',
        'reverted_at',
        'reverted_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'reverted_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reverter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(Record::class, 'import_batch_id');
    }
}