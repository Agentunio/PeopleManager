<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkerShift extends Model
{
    public static function parseTimeToMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    protected $table = 'worker_shifts';

    public $timestamps = true;

    protected $fillable = [
        'worker_id',
        'day',
        'shift_type',
        'package_id',
        'minutes',
        'status',
        'substituted_for_shift_id',
        'worker_from_time',
        'worker_to_time',
        'hours_source',
        'is_draft',
    ];

    protected function casts(): array
    {
        return [
            'is_draft' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_draft', false);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('is_draft', true);
    }

    public function getWorkerFromHourAttribute(): ?int
    {
        return $this->worker_from_time !== null ? intdiv($this->worker_from_time, 60) : null;
    }

    public function getWorkerFromMinuteAttribute(): ?int
    {
        return $this->worker_from_time !== null ? $this->worker_from_time % 60 : null;
    }

    public function getWorkerToHourAttribute(): ?int
    {
        return $this->worker_to_time !== null ? intdiv($this->worker_to_time, 60) : null;
    }

    public function getWorkerToMinuteAttribute(): ?int
    {
        return $this->worker_to_time !== null ? $this->worker_to_time % 60 : null;
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function substitutedForShift(): BelongsTo
    {
        return $this->belongsTo(self::class, 'substituted_for_shift_id');
    }

    public function substitute(): HasOne
    {
        return $this->hasOne(self::class, 'substituted_for_shift_id');
    }
}
